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
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Collection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Theme\Block\Html\Pager as MagentoPager;
use Magento\Store\Model\Store;
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

    /** @var Http */
    public $httpRequest;

    /**  @var StoreManagerInterface */
    public $storeManager;

    /** @var NostoCmpHelperData */
    public $nostoCmpHelperData;

    /** @var NostoHelperAccount */
    public $nostoHelperAccount;

    /** @var NostoLogger */
    public $logger;

    /**
     * AbstractBlock constructor.
     * @param Context $context
     * @param Http $httpRequest
     * @param NostoCmpHelperData $nostoCmpHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoLogger $logger
     */
    public function __construct(
        Context $context,
        Http $httpRequest,
        NostoCmpHelperData $nostoCmpHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoLogger $logger
    ) {
        $this->httpRequest = $httpRequest;
        $this->nostoCmpHelperData = $nostoCmpHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->logger = $logger;
        $this->storeManager = $context->getStoreManager();
        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function isCmpCurrentSortOrder()
    {
        try {
            /* @var Store $store */
            $store = $this->storeManager->getStore();
        } catch (NoSuchEntityException $e) {
            $this->logger->info('Cannot get store');
            return false;
        }

        $currentOrder = $this->getCurrentOrder();
        if ($currentOrder !== null
            && $currentOrder === NostoHelperSorting::NOSTO_PERSONALIZED_KEY
            && $this->nostoHelperAccount->nostoInstalledAndEnabled($store)
            && $this->nostoCmpHelperData->isCategorySortingEnabled($store)
        ) {
            return true;
        }
        return false;
    }

    /**
     * @return string|null
     */
    private function getCurrentOrder()
    {
        return $this->httpRequest->getParam('product_list_order');
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
     * @param MagentoToolbar|MagentoPager $block
     * @param $result
     * @return float|int
     */
    public function afterGetFirstNum($block, $result)
    {
        if ($this->isCmpCurrentSortOrder()) {
            $pageSize = $block->getCollection()->getPageSize();
            $currentPage = $this->getCurrentPageNumber();
            return $pageSize * ($currentPage - 1) + 1;
        }
        return $result;
    }

    /**
     * @param MagentoToolbar|MagentoPager $block
     * @param $result
     * @return float|int
     */
    public function afterGetLastNum($block, $result)
    {
        if ($this->isCmpCurrentSortOrder()) {
            $pageSize = $block->getCollection()->getPageSize();
            $currentPage = $this->getCurrentPageNumber();
            $totalResultOfPage = $block->getCollection()->count();
            return $pageSize * ($currentPage - 1) + $totalResultOfPage;
        }
        return $result;
    }

    /**
     * @param MagentoToolbar|MagentoPager $block
     * @param $result
     * @return int
     */
    public function afterGetTotalNum($block, $result)
    {
        if ($this->isCmpCurrentSortOrder()) {
            return $this->getTotalProducts();
        }
        return $result;
    }

    /**
     * @param MagentoToolbar|MagentoPager $block
     * @param $result
     * @return int
     */
    public function afterGetLastPageNum($block, $result)
    {
        if ($this->isCmpCurrentSortOrder()) {
            return $this->getLastPageNumber();
        }
        return $result;
    }

    /**
     * @param Collection $collection
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
        return (int)$this->httpRequest->getParam('p', '1');
    }
}