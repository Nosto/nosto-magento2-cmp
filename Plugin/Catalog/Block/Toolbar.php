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
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\App\Request\Http;
use Magento\Framework\DB\Select;
use Magento\LayeredNavigation\Block\Navigation\State;
use Nosto\Cmp\Exception\MissingCookieException;
use Nosto\Cmp\Helper\Data as NostoCmpHelperData;
use Nosto\Cmp\Helper\SearchEngine;
use Nosto\Cmp\Model\Facet\FacetInterface;
use Nosto\Cmp\Model\Service\Facet\BuildWebFacetService;
use Nosto\Cmp\Model\Service\Recommendation\StateAwareCategoryService;
use Nosto\Cmp\Utils\CategoryMerchandising as CategoryMerchandisingUtil;
use Nosto\Helper\ArrayHelper as NostoHelperArray;
use Nosto\NostoException;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger;
use Zend_Db_Expr;

class Toolbar extends AbstractBlock
{

    /** @var State */
    private $state;

    /** @var BuildWebFacetService */
    private $buildWebFacetService;

    /** @var Http */
    private $request;

    private static $isProcessed = false;

    /** @var int */
    private $pageSize;

    /**
     * Toolbar constructor.
     * @param Context $context
     * @param NostoCmpHelperData $nostoCmpHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param StateAwareCategoryService $categoryService
     * @param ParameterResolverInterface $parameterResolver
     * @param Http $request
     * @param Logger $logger
     * @param SearchEngine $searchEngineHelper
     * @param BuildWebFacetService $buildWebFacetService
     * @param State $state
     * @param int $pageSize
     */
    public function __construct(
        Context $context,
        NostoCmpHelperData $nostoCmpHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        StateAwareCategoryService $categoryService,
        ParameterResolverInterface $parameterResolver,
        Http $request,
        Logger $logger,
        SearchEngine $searchEngineHelper,
        BuildWebFacetService $buildWebFacetService,
        State $state,
        $pageSize
    ) {
        $this->buildWebFacetService = $buildWebFacetService;
        $this->state = $state;
        $this->request = $request;
        $this->pageSize = $pageSize;
        parent::__construct(
            $context,
            $parameterResolver,
            $nostoCmpHelperData,
            $nostoHelperAccount,
            $nostoHelperScope,
            $categoryService,
            $searchEngineHelper,
            $logger
        );
    }

    /**
     * Plugin - Used to modify default Sort By filters
     *
     * @param MagentoToolbar $subject
     * @return MagentoToolbar
     */
    public function afterSetCollection(// phpcs:ignore EcgM2.Plugins.Plugin.PluginWarning
        MagentoToolbar $subject
    ) {
        if (self::$isProcessed || !$this->searchEngineHelper->isMysql()) {
            $this->getLogger()->debugWithSource(
                sprintf(
                    'Skipping toolbar handling, processed flag is %s, search engine in use "%s"',
                    (string) self::$isProcessed,
                    $this->searchEngineHelper->getCurrentEngine()
                ),
                [],
                $this
            );
            return $subject;
        }
        $store = $this->getNostoHelperScope()->getStore();
        if ($this->isCmpCurrentSortOrder($store) && $this->isCategoryPage()) {
            try {
                /* @var ProductCollection $subjectCollection */
                $subjectCollection = $subject->getCollection();
                if (!$subjectCollection instanceof ProductCollection) {
                    throw new NostoException(
                        "Collection is not instanceof ProductCollection"
                    );
                }
                $result = $this->getCmpResult(
                    $this->buildWebFacetService->getFacets(),
                    $this->getCurrentPageNumber()-1,
                    $this->getPageSize($subjectCollection)
                );
                $nostoProductIds = CategoryMerchandisingUtil::parseProductIds($result);
                if (!empty($nostoProductIds)
                    && NostoHelperArray::onlyScalarValues($nostoProductIds)
                ) {
                    $nostoProductIds = array_reverse($nostoProductIds);
                    $this->sortByProductIds($subjectCollection, $nostoProductIds);
                    $this->whereInProductIds($subjectCollection, $nostoProductIds);
                    $this->getLogger()->debugWithSource(
                        $subjectCollection->getSelectSql()->__toString(),
                        [],
                        $this
                    );
                } else {
                    $this->getLogger()->debugWithSource(
                        'Got an empty CMP result from Nosto for category',
                        [],
                        $this
                    );
                }
            } catch (MissingCookieException $e) {
                $this->getLogger()->debugWithSource(
                    $e->getMessage(),
                    [],
                    $this
                );
            } catch (Exception $e) {
                $this->getLogger()->exception($e);
            }
        }
        self::$isProcessed = true;
        return $subject;
    }

    /**
     * @param FacetInterface $facets
     * @param $start
     * @param $limit
     * @return CategoryMerchandisingResult|null
     * @throws MissingCookieException
     * @throws NostoException
     */
    private function getCmpResult(FacetInterface $facets, $start, $limit)
    {
        return $this->getCategoryService()->getPersonalisationResult(
            $facets,
            $start,
            $limit
        );
    }

    /**
     * @param ProductCollection $subjectCollection
     * @return int
     */
    private function getPageSize($subjectCollection)
    {
        if ($this->pageSize != -1) {
            $this->getLogger()->debugWithSource(
                sprintf(
                    'Using DI value (%s) for the page size',
                    $this->pageSize
                ),
                [],
                $this
            );

            return $this->pageSize;
        }

        return $subjectCollection->getPageSize();
    }

    /**
     * @return bool
     */
    private function isCategoryPage()
    {
        return $this->request->getFullActionName() == 'catalog_category_view';
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
}
