<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Cmp\Plugin\Catalog\Block;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Block\Product\ProductList\Toolbar as MagentoToolbar;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\NoSuchEntityException;
use /** @noinspection PhpDeprecationInspection */
    Magento\Framework\Registry;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\LayeredNavigation\Block\Navigation\State;
use Magento\Store\Model\Store;
use Nosto\Cmp\Helper\Data as NostoCmpHelperData;
use Nosto\Cmp\Helper\SearchEngine;
use Nosto\Cmp\Model\Filter\FilterBuilder as NostoFilterBuilder;
use Nosto\Cmp\Model\Service\Recommendation\StateAwareCategoryService;
use Nosto\Cmp\Plugin\Catalog\Model\Product as NostoProductPlugin;
use Nosto\Cmp\Utils\CategoryMerchandising as CategoryMerchandisingUtil;
use Nosto\Cmp\Utils\Debug\Product as ProductDebug;
use Nosto\Helper\ArrayHelper as NostoHelperArray;
use Nosto\NostoException;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Service\Product\Category\DefaultCategoryService as CategoryBuilder;
use Zend_Db_Expr;

class Toolbar extends AbstractBlock
{

    /**  @var CategoryBuilder */
    private $categoryBuilder;

    /** @var Registry */
    private $registry;

    /** @var CookieManagerInterface */
    private $cookieManager;

    /** @var State */
    private $state;

    /** @var NostoFilterBuilder  */
    private $nostoFilterBuilder;

    /** @var SearchEngine */
    private $searchEngineHelper;

    private static $isProcessed = false;

    /**
     * Toolbar constructor.
     * @param Context $context
     * @param NostoCmpHelperData $nostoCmpHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param CategoryBuilder $builder
     * @param StateAwareCategoryService $categoryService
     * @param CookieManagerInterface $cookieManager
     * @param ParameterResolverInterface $parameterResolver
     * @param NostoLogger $logger
     * @param NostoFilterBuilder $nostoFilterBuilder
     * @param Registry $registry
     * @param State $state
     * @param SearchEngine $searchEngineHelper
     */
    public function __construct(
        Context $context,
        NostoCmpHelperData $nostoCmpHelperData,
        NostoHelperAccount $nostoHelperAccount,
        CategoryBuilder $builder,
        StateAwareCategoryService $categoryService,
        CookieManagerInterface $cookieManager,
        ParameterResolverInterface $parameterResolver,
        NostoLogger $logger,
        NostoFilterBuilder $nostoFilterBuilder,
        Registry $registry,
        State $state,
        SearchEngine $searchEngineHelper
    ) {
        $this->categoryBuilder = $builder;
        $this->storeManager = $context->getStoreManager();
        $this->cookieManager = $cookieManager;
        $this->nostoFilterBuilder = $nostoFilterBuilder;
        $this->registry = $registry;
        $this->state = $state;
        $this->searchEngineHelper = $searchEngineHelper;
        parent::__construct(
            $context,
            $parameterResolver,
            $nostoCmpHelperData,
            $nostoHelperAccount,
            $categoryService,
            $logger
        );
    }

    /**
     * Plugin - Used to modify default Sort By filters
     *
     * @param MagentoToolbar $subject
     * @return MagentoToolbar
     * @throws NoSuchEntityException
     */
    public function afterSetCollection(// phpcs:ignore EcgM2.Plugins.Plugin.PluginWarning
        MagentoToolbar $subject
    ) {
        if (!$this->searchEngineHelper->isMysql()) {
            return $subject;
        }
        if (self::$isProcessed) {
            return $subject;
        }
        /* @var Store $store */
        $store = $this->storeManager->getStore();
        if ($this->isCmpCurrentSortOrder($store)) {
            try {
                /* @var ProductCollection $subjectCollection */
                $subjectCollection = $subject->getCollection();
                if (!$subjectCollection instanceof ProductCollection) {
                    throw new NostoException(
                        "Collection is not instanceof ProductCollection"
                    );
                }
                $result = $this->getCmpResult($store); //@phan-suppress-current-line PhanTypeMismatchArgument
                //Get ids of products to order
                $nostoProductIds = CategoryMerchandisingUtil::parseProductIds($result);
                if (!empty($nostoProductIds)
                    && NostoHelperArray::onlyScalarValues($nostoProductIds)
                ) {
                    ProductDebug::getInstance()->setProductIds($nostoProductIds);
                    $nostoProductIds = array_reverse($nostoProductIds);
                    $this->sortByProductIds($subjectCollection, $nostoProductIds);
                    $this->whereInProductIds($subjectCollection, $nostoProductIds);
                    $this->logger->debug(
                        $subjectCollection->getSelectSql()->__toString(),
                        ['nosto' => 'cmp']
                    );
                    $this->addTrackParamToProduct($subjectCollection, $nostoProductIds);
                } else {
                    $this->logger->info(sprintf(
                        "CMP result is empty for category: %s",
                        $this->getCurrentCategoryString($store) //@phan-suppress-current-line PhanTypeMismatchArgument
                    ));
                }
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
        }
        self::$isProcessed = true;
        return $subject;
    }

    /**
     * @return CategoryMerchandisingResult
     * @throws NostoException
     */
    private function getCmpResult()
    {
        return $this->getCategoryService()->getPersonalisationResult(
            $this->getCurrentPageNumber() - 1,
            $this->getLimit()
        );
    }

    /**
     * @param ProductCollection $collection
     * @param array $nostoProductIds
     */
    private function sortByProductIds(ProductCollection $collection, array $nostoProductIds)
    {
        $select = $collection->getSelect();
        $select->reset(Select::ORDER);
        $zendExpression = [
            new Zend_Db_Expr('FIELD(e.entity_id,' . implode(',', $nostoProductIds) . ') DESC')
        ];
        $select->order($zendExpression);
    }

    /**
     * @param ProductCollection $collection
     * @param array $nostoProductIds
     */
    private function whereInProductIds(ProductCollection $collection, array $nostoProductIds)
    {
        $select = $collection->getSelect();
        $zendExpression = new Zend_Db_Expr(
            'e.entity_id IN (' . implode(',', $nostoProductIds) . ')'
        );
        $select->where($zendExpression);
    }

    /**
     * @param ProductCollection $collection
     * @param array $nostoProductIds
     */
    private function addTrackParamToProduct(ProductCollection $collection, array $nostoProductIds)
    {
        $collection->each(static function ($product) use ($nostoProductIds) {
            /* @var Product $product */
            if (in_array($product->getId(), $nostoProductIds, true)) {
                $product->setData(NostoProductPlugin::NOSTO_TRACKING_PARAMETER_NAME, true);
            }
        });
    }
}
