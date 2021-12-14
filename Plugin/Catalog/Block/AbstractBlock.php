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

use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Block\Product\ProductList\Toolbar as MagentoToolbar;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\Store;
use Magento\Theme\Block\Html\Pager as MagentoPager;
use Nosto\Cmp\Helper\CategorySorting as NostoHelperSorting;
use Nosto\Cmp\Helper\Data as NostoCmpHelperData;
use Nosto\Cmp\Helper\SearchEngine;
use Nosto\Cmp\Model\Service\Recommendation\StateAwareCategoryService;
use Nosto\Cmp\Model\Service\Recommendation\StateAwareCategoryServiceInterface;
use Nosto\Cmp\Utils\Traits\LoggerTrait;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger;

abstract class AbstractBlock extends Template
{
    use LoggerTrait {
        LoggerTrait::__construct as loggerTraitConstruct; // @codingStandardsIgnoreLine
    }

    /** @var SearchEngine */
    protected $searchEngineHelper;

    /** @var int */
    private $lastPageNumber;

    /** @var ParameterResolverInterface */
    private $paramResolver;

    /** @var NostoCmpHelperData */
    private $nostoCmpHelperData;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperScope */
    private $nostoHelperScope;

    /** @var string */
    public static $currentOrder;

    /**
     * @var StateAwareCategoryService
     */
    private $categoryService;

    /**
     * AbstractBlock constructor.
     * @param Context $context
     * @param ParameterResolverInterface $parameterResolver
     * @param NostoCmpHelperData $nostoCmpHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param StateAwareCategoryServiceInterface $categoryService
     * @param SearchEngine $searchEngineHelper
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        ParameterResolverInterface $parameterResolver,
        NostoCmpHelperData $nostoCmpHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        StateAwareCategoryServiceInterface $categoryService,
        SearchEngine $searchEngineHelper,
        Logger $logger
    ) {
        $this->loggerTraitConstruct(
            $logger
        );
        $this->categoryService = $categoryService;
        $this->paramResolver = $parameterResolver;
        $this->nostoCmpHelperData = $nostoCmpHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->searchEngineHelper = $searchEngineHelper;
        parent::__construct($context);
    }

    /**
     * Checks if current sorting order is Nosto's `Personalized for you`
     * and category sorting is enabled
     *
     * @param Store $store
     * @return bool
     */
    public function isCmpCurrentSortOrder(Store $store)
    {
        $currentOrder = $this->getCurrentOrder();
        if ($currentOrder === null) {
            return false;
        }
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
        $categoryMerchandisingResult = $this->getCategoryService()->getLastResult();
        if (!empty($categoryMerchandisingResult->getTrackingCode())) {
            return true;
        }
        return false;
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
     * @return int
     */
    public function getTotalProducts()
    {
        return $this->getCategoryService()->getLastResult()->getTotalPrimaryCount();
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
        if ($this->isCmpTakingOverCatalog() && $this->searchEngineHelper->isMysql()) {
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
        if ($this->isCmpTakingOverCatalog() && $this->searchEngineHelper->isMysql()) {
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
        if ($this->isCmpTakingOverCatalog() && $this->searchEngineHelper->isMysql()) {
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
        if ($this->isCmpTakingOverCatalog() && $this->searchEngineHelper->isMysql()) {
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
        $this->lastPageNumber = (int) ceil($this->getTotalProducts() / $this->getLimit());
        return $this->lastPageNumber;
    }

    /**
     * @return int
     */
    private function getLimit()
    {
        return $this->getCategoryService()->getLastUsedLimit();
    }

    /**
     * @return int
     */
    public function getCurrentPageNumber()
    {
        return $this->paramResolver->getCurrentPage();
    }

    /**
     * @return StateAwareCategoryService
     */
    public function getCategoryService(): StateAwareCategoryService
    {
        return $this->categoryService;
    }

    /**
     * @return NostoHelperScope
     */
    public function getNostoHelperScope(): NostoHelperScope
    {
        return $this->nostoHelperScope;
    }
}
