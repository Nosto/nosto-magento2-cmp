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

namespace Nosto\Cmp\Plugin\Http;

use Exception;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Http\Context as MagentoContext;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Cmp\Block\SegmentMapping;
use Nosto\Cmp\Helper\CategorySorting as NostoHelperSorting;
use Nosto\Cmp\Helper\Data as NostoCmpHelperData;
use Nosto\Cmp\Plugin\Catalog\Block\DefaultParameterResolver as ParamResolver;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Service\Product\Category\DefaultCategoryService as CategoryBuilder;

class Context
{
    /** @var CookieManagerInterface  */
    private $cookieManager;

    /** @var Http  */
    private $request;

    /** @var CategoryFactory  */
    private $categoryFactory;

    /** @var String */
    private $categoryString;

    /** @var CategoryBuilder  */
    private $categoryBuilder;

    /** @var StoreManagerInterface  */
    private $storeManager;

    /** @var NostoHelperAccount  */
    private $nostoHelperAccount;

    /** @var NostoCmpHelperData  */
    private $nostoCmpHelperData;

    /** @var Store */
    private $store;

    /** @var NostoLogger  */
    private $logger;
    /**
     * @var Session
     */
    private $customerSession;

    /**
     * Context constructor.
     * @param Session $customerSession
     * @param CookieManagerInterface $cookieManager
     * @param CategoryFactory $categoryFactory
     * @param CategoryBuilder $categoryBuilder
     * @param StoreManagerInterface $storeManager
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoCmpHelperData $nostoCmpHelperData
     * @param Http $request
     * @param NostoLogger $logger
     */
    public function __construct(
        Session $customerSession,
        CookieManagerInterface $cookieManager,
        CategoryFactory $categoryFactory,
        CategoryBuilder $categoryBuilder,
        StoreManagerInterface $storeManager,
        NostoHelperAccount $nostoHelperAccount,
        NostoCmpHelperData $nostoCmpHelperData,
        Http $request,
        NostoLogger $logger
    ) {
        $this->customerSession = $customerSession;
        $this->cookieManager = $cookieManager;
        $this->categoryFactory = $categoryFactory;
        $this->categoryBuilder = $categoryBuilder;
        $this->storeManager = $storeManager;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoCmpHelperData = $nostoCmpHelperData;
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * @param MagentoContext $subject
     * @return MagentoContext
     */
    // phpcs:ignore EcgM2.Plugins.Plugin
    public function beforeGetVaryString(MagentoContext $subject)
    {
        try {
            $this->setCategoryAndStore();
        } catch (Exception $e) {
            $this->logger->exception($e);
        }
        $sortingParameter = $this->request->getParam(ParamResolver::DEFAULT_SORTING_ORDER_PARAM);
        if ($this->isCategoryPage() &&
            $sortingParameter &&
            $sortingParameter === NostoHelperSorting::NOSTO_PERSONALIZED_KEY &&
            $this->nostoHelperAccount->nostoInstalledAndEnabled($this->store) &&
            $this->nostoCmpHelperData->isCategorySortingEnabled($this->store)) {

            $variation = $this->getSegmentFromCookie();
            if ($variation === '') {
                $this->logger->debug('Variation key is empty');
            }
            $subject->setValue('CONTEXT_NOSTO', $variation, $defaultValue = "");
        }
        return $subject;
    }

    /**
     * Get segment id from cookie
     * @return string
     */
    private function getSegmentFromCookie()
    {
        //Read cookie
        $cookie = $this->cookieManager->getCookie(SegmentMapping::COOKIE_CATEGORY_MAP);
        if ($cookie === null) {
            $this->logger->debug(sprintf(
                'Cookie %s is not present',
                SegmentMapping::COOKIE_CATEGORY_MAP
            ));
            return '';
        }
        //Parse value
        $stdClass = json_decode($cookie);
        if ($stdClass === null) {
            $this->logger->debug(sprintf(
                'Cookie %s has no value',
                SegmentMapping::COOKIE_CATEGORY_MAP
            ));
            return '';
        }
        $segmentMap = get_object_vars($stdClass);
        $signedInteger = crc32($this->categoryString);
        $unsignedInteger = (int) sprintf("%u", $signedInteger);
        $hashedCategory = dechex($unsignedInteger);
        //Check if current category is part of segment mapping
        if (array_key_exists($hashedCategory, $segmentMap) &&
            is_numeric($segmentMap[$hashedCategory])) {
            $index = $segmentMap[$hashedCategory];
            $indexedIds = $this->cookieManager->getCookie(SegmentMapping::COOKIE_SEGMENT_MAP);
            if ($indexedIds === null || $indexedIds === '') {
                return '';
            }
            $indexedIds = json_decode($indexedIds);
            return $indexedIds[$index];
        }
        return '';
    }

    /**
     * @throws NoSuchEntityException
     */
    private function setCategoryAndStore()
    {
        $category = $this->getCategory();
        if ($category) {
            $this->store = $this->storeManager->getStore();
            $this->categoryString = strtolower(
                $this->categoryBuilder->getCategory($category, $this->store)
            );
        }
    }

    /**
     * Checks if the current page is a category page
     * @return bool
     */
    private function isCategoryPage()
    {
        if (is_string($this->categoryString)) {
            return true;
        }
        return false;
    }

    /**
     * Return category object or false if not found
     * @return null|Category
     */
    private function getCategory()
    {
        $categoryFactory = $this->categoryFactory->create();
        $urlPath = $this->getUrlPath();
        if (!is_string($urlPath)) {
            return null;
        }
        return $categoryFactory->loadByAttribute('url_path', $urlPath);
    }

    /**
     * @return null|string
     */
    private function getUrlPath()
    {
        $path = $this->request->getUri()->getPath();
        if ($path === null) {
            return null;
        }
        //Remove leading slash
        $path = substr($path, 1);
        if (!is_string($path)) {
            return null;
        }
        //Remove . ending
        $path = explode(".", $path)[0];
        return $path;
    }
}
