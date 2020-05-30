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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Theme\Block\Html\Pager as MagentoPager;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Cmp\Helper\CategorySorting as NostoHelperSorting;
use Nosto\Cmp\Helper\Data as NostoCmpHelperData;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Logger\Logger as NostoLogger;

abstract class AbstractBlock extends Template
{
    /** @var int */
    public static $totalProducts;

    /** @var int */
    public static $limit;

    /** @var int */
    private $lastPageNumber;

    /** @var ParameterResolverInterface */
    public $paramResolver;

    /**  @var StoreManagerInterface */
    public $storeManager;

    /** @var NostoCmpHelperData */
    public $nostoCmpHelperData;

    /** @var NostoHelperAccount */
    public $nostoHelperAccount;

    /** @var NostoLogger */
    public $logger;

    /** @var string */
    public static $currentOrder;

    /** @var bool */
    public static $catalogTakeover;

    /**
     * AbstractBlock constructor.
     * @param Context $context
     * @param ParameterResolverInterface $parameterResolver
     * @param NostoCmpHelperData $nostoCmpHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoLogger $logger
     */
    public function __construct(
        Context $context,
        ParameterResolverInterface $parameterResolver,
        NostoCmpHelperData $nostoCmpHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoLogger $logger
    ) {
        $this->paramResolver = $parameterResolver;
        $this->nostoCmpHelperData = $nostoCmpHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->logger = $logger;
        $this->storeManager = $context->getStoreManager();
        parent::__construct($context);
    }

    /**
     * Checks if current sorting order is Nosto's `Personalized for you`
     * and category sorting is enabled
     *
     * @return bool
     */
    public function isCmpCurrentSortOrder()
    {
        try {
            $store = $this->storeManager->getStore();
        } catch (NoSuchEntityException $e) {
            $this->logger->info('Cannot get store');
            return false;
        }
        $currentOrder = $this->getCurrentOrder();
        if ($currentOrder === null) {
            return false;
        }
		/** @noinspection PhpParamsInspection */
		if ($currentOrder === NostoHelperSorting::NOSTO_PERSONALIZED_KEY
            //@phan-suppress-next-line PhanTypeMismatchArgument
            && $this->nostoHelperAccount->nostoInstalledAndEnabled($store)
            && $this->nostoCmpHelperData->isCategorySortingEnabled($store)
        ) {
            return true;
        }
        return false;
    }

    /**
     * In case CMP is selected as sorting order
     * and result is not empty
     *
     * @return bool
     */
    public function isCmpTakingOverCatalog()
    {
        if (self::$catalogTakeover === null) {
            self::$catalogTakeover = $this->isCmpResult();
        }
        return self::$catalogTakeover;
    }

    /**
     * @return bool
     */
    private function isCmpResult()
    {
        if (!$this->isCmpCurrentSortOrder() ||
            (self::$totalProducts === 0 || self::$totalProducts === null)
           ) {
            return false;
        }
        return true;
    }

    /**
     * @return string
     */
    private function getCurrentOrder()
    {
        if (self::$currentOrder === null) {
            self::$currentOrder = $this->paramResolver->getSortingOrder();
        }
        return self::$currentOrder;
    }

    /**
     * @param int $totalProducts
     */
    public function setTotalProducts($totalProducts)
    {
        self::$totalProducts = $totalProducts;
    }

    /**
     * @return int
     */
    public function getTotalProducts()
    {
        return self::$totalProducts;
    }

    /**
     * Return order number of first product of the page
     *
     * @param MagentoToolbar|MagentoPager $block
     * @param $result
     * @return float|int
     */
    public function afterGetFirstNum($block, $result)
    {
        if ($this->isCmpTakingOverCatalog()) {
            $pageSize = $block->getCollection()->getPageSize();
            $currentPage = $this->getCurrentPageNumber();
            return $pageSize * ($currentPage - 1) + 1;
        }
        return $result;
    }

    /**
     * Return order number of last product of the page
     *
     * @param MagentoToolbar|MagentoPager $block
     * @param $result
     * @return float|int
     */
    public function afterGetLastNum($block, $result)
    {
        if ($this->isCmpTakingOverCatalog()) {
            $pageSize = $block->getCollection()->getPageSize();
            $currentPage = $this->getCurrentPageNumber();
            $totalResultOfPage = $block->getCollection()->getSize();
            return $pageSize * ($currentPage - 1) + $totalResultOfPage;
        }
        return $result;
    }

    /**
     * @param MagentoToolbar|MagentoPager $block
     * @param $result
     * @return int
	 * @noinspection PhpUnusedParameterInspection
	 */
    public function afterGetTotalNum($block, $result) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        if ($this->isCmpTakingOverCatalog()) {
            return $this->getTotalProducts();
        }
        return $result;
    }

    /**
     * @param MagentoToolbar|MagentoPager $block
     * @param $result
     * @return int
	 * @noinspection PhpUnusedParameterInspection
	 */
    public function afterGetLastPageNum($block, $result) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        if ($this->isCmpTakingOverCatalog()) {
            return $this->getLastPageNumber();
        }
        return $result;
    }

    /**
     * @return int
     */
    public function getLastPageNumber()
    {
        if ($this->lastPageNumber !== null) {
            return $this->lastPageNumber;
        }
        $this->lastPageNumber = (int) ceil(self::$totalProducts/self::$limit);
        return $this->lastPageNumber;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return self::$limit;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit)
    {
        self::$limit = $limit;
    }

    /**
     * @return int
     */
    public function getCurrentPageNumber()
    {
        return $this->paramResolver->getCurrentPage();
    }
}
