<?php
/**
 * Copyright (c) 2019, Nosto Solutions Ltd
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
 * @copyright 2019 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Cmp\Plugin\Catalog\Block;

use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Block\Product\ProductList\Toolbar as MagentoToolbar;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as FullTextCollection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Cmp\Helper\CategorySorting as NostoHelperSorting;
use Nosto\Cmp\Helper\Data as NostoCmpHelperData;
use Nosto\Cmp\Model\Service\Recommendation\Category as CategoryRecommendation;
use Nosto\Helper\ArrayHelper as NostoHelperArray;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\CategoryString\Builder as CategoryBuilder;
use Nosto\Tagging\Model\Customer\Customer as NostoCustomer;

class Toolbar extends Template
{
    /**  @var StoreManagerInterface */
    private $storeManager;

    /** @var NostoCmpHelperData */
    private $nostoCmpHelperData;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /**  @var CategoryBuilder */
    private $categoryBuilder;

    /** @var Registry */
    private $registry;

    /** @var CookieManagerInterface */
    private $cookieManager;

    /** @var CategoryRecommendation */
    private $categoryRecommendation;

    /** @var NostoLogger */
    private $logger;

    /**
     * Toolbar constructor.
     * @param Context $context
     * @param NostoCmpHelperData $nostoCmpHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param CategoryBuilder $builder
     * @param CategoryRecommendation $categoryRecommendation
     * @param CookieManagerInterface $cookieManager
     * @param NostoLogger $logger
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        NostoCmpHelperData $nostoCmpHelperData,
        NostoHelperAccount $nostoHelperAccount,
        CategoryBuilder $builder,
        CategoryRecommendation $categoryRecommendation,
        CookieManagerInterface $cookieManager,
        NostoLogger $logger,
        Registry $registry,
        array $data = []
    ) {
        $this->nostoCmpHelperData = $nostoCmpHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->categoryBuilder = $builder;
        $this->storeManager = $context->getStoreManager();
        $this->cookieManager = $cookieManager;
        $this->categoryRecommendation = $categoryRecommendation;
        $this->logger = $logger;
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Plugin - Used to modify default Sort By filters
     *
     * @param MagentoToolbar $subject
     * @return MagentoToolbar
     * @throws NoSuchEntityException
     */
    public function afterSetCollection(
        MagentoToolbar $subject
    ) {
        $store = $this->storeManager->getStore();
        $currentOrder = $subject->getCurrentOrder();
        if (($currentOrder === NostoHelperSorting::NOSTO_PERSONALIZED_KEY
            || $currentOrder === NostoHelperSorting::NOSTO_TOPLIST_KEY)
            && $this->nostoHelperAccount->nostoInstalledAndEnabled($store)
            && $this->nostoCmpHelperData->isCategorySortingEnabled($store)
        ) {
            try {
                //Get ids of products to order
                $orderIds = $this->getSortedIds($store, $currentOrder);
                if ($subject->getCollection() instanceof  FullTextCollection
                    && !empty($orderIds)
                    && NostoHelperArray::onlyScalarValues($orderIds)
                ) {
                    $orderIds = array_reverse($orderIds);
                    $this->manipulateSQL($subject->getCollection(), $orderIds);
                }
            } catch (\Exception $e) {
                $this->logger->exception($e);
            }
        }
        return $subject;
    }

    /**
     * @param Store $store
     * @param $type
     * @return array
     * @throws NostoException
     */
    private function getSortedIds(Store $store, $type)
    {
        $nostoAccount = $this->nostoHelperAccount->findAccount($store);
        if ($nostoAccount === null) {
            throw new NostoException('Account cannot be null');
        }
        $category = $this->registry->registry('current_category');
        $categoryString = $this->categoryBuilder->build($category, $store);
        $nostoCustomer = $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME);
        return $this->categoryRecommendation->getSortedProductIds(
            $nostoAccount,
            $nostoCustomer,
            $categoryString,
            $type
        );
    }

    /**
     * Manipulate SQL to adapt to CMP logic
     *
     * @param FullTextCollection $collection
     * @param array $ids
     */
    private function manipulateSQL(FullTextCollection $collection, array $ids)
    {
        $select = $collection->getSelect();
        $zendExpression = new \Zend_Db_Expr('e.entity_id IN ( ' . implode(',', $ids) . ' )');
        $select->where($zendExpression);
        $zendExpression = new \Zend_Db_Expr('FIELD(e.entity_id,' . implode(',', $ids) . ') DESC');
        $select->order($zendExpression);
    }
}
