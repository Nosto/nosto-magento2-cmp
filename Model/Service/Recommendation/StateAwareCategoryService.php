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

namespace Nosto\Cmp\Model\Service\Recommendation;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\LayeredNavigation\Block\Navigation\State;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Cmp\Logger\LoggerInterface;
use Nosto\Cmp\Model\Filter\WebFilters;
use Nosto\Cmp\Model\Filter\FiltersInterface;
use Nosto\Cmp\Utils\CategoryMerchandising;
use Nosto\Cmp\Utils\Debug\Product as ProductDebug;
use Nosto\Cmp\Utils\Debug\ServerTiming;
use Nosto\NostoException;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Service\FeatureAccess;
use Nosto\Tagging\Helper\Account;
use Nosto\Tagging\Model\Customer\Customer as NostoCustomer;
use Nosto\Tagging\Model\Service\Product\Category\DefaultCategoryService as CategoryBuilder;
use Magento\Catalog\Api\CategoryRepositoryInterface;

class StateAwareCategoryService implements StateAwareCategoryServiceInterface
{
    const NOSTO_PREVIEW_COOKIE = 'nostopreview';
    const MAX_PRODUCT_AMOUNT = 100;
    const TIME_PROF_GRAPHQL_QUERY = 'cmp_graphql_query';

    /**
     * @var Category
     */
    private $categoryService;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var WebFilters
     */
    private $filterBuilder;

    /**
     * @var Account
     */
    private $accountHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var CategoryBuilder
     */
    private $categoryBuilder;

    /**
     * @var CategoryMerchandisingResult|null
     */
    private $lastResult = null;

    /**
     * @var int
     */
    private $lastUsedLimit;

    /**
     * @var int
     */
    private $lastUsedPage;

    /** @var CategoryRepositoryInterface */
    private $categoryRepository;

    /**
     * Category constructor.
     * @param CookieManagerInterface $cookieManager
     * @param Category $categoryService
     * @param State $state
     * @param WebFilters $filterBuilder
     * @param Account $nostoHelperAccount
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     * @param CategoryBuilder $categoryBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        Category $categoryService,
        WebFilters $filterBuilder,
        Account $nostoHelperAccount,
        StoreManagerInterface $storeManager,
        Registry $registry,
        CategoryBuilder $categoryBuilder,
        CategoryRepositoryInterface $categoryRepository,
        LoggerInterface $logger
    ) {
        $this->cookieManager = $cookieManager;
        $this->categoryService = $categoryService;
        $this->filterBuilder = $filterBuilder;
        $this->cookieManager = $cookieManager;
        $this->accountHelper = $nostoHelperAccount;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->registry = $registry;
        $this->categoryBuilder = $categoryBuilder;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @inheritDoc
     * @throws NostoException
     * @throws LocalizedException
     */
    public function getPersonalisationResult(
        FiltersInterface $filters,
        $pageNumber,
        $limit
    ): ?CategoryMerchandisingResult {
        $store = $this->storeManager->getStore();
        $category = $this->getCurrentCategoryString($store);
        //@phan-suppress-next-line PhanTypeMismatchArgument
        $nostoAccount = $this->accountHelper->findAccount($store);
        if ($nostoAccount === null) {
            throw new NostoException('Account cannot be null');
        }
        $featureAccess = new FeatureAccess($nostoAccount);
        if (!$featureAccess->canUseGraphql()) {
            throw new NostoException('Missing Nosto API_APPS token');
        }

        $previewMode = (bool)$this->cookieManager->getCookie(self::NOSTO_PREVIEW_COOKIE);
        $this->lastResult = ServerTiming::getInstance()->instrument(
            function () use ($nostoAccount, $previewMode, $category, $pageNumber, $limit, $filters) {
                return $this->categoryService->getPersonalisationResult(
                    $nostoAccount,
                    $filters,
                    $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME),
                    $category,
                    $pageNumber,
                    $limit,
                    $previewMode
                );
            },
            self::TIME_PROF_GRAPHQL_QUERY
        );
        $this->lastUsedLimit = $limit;
        $this->lastUsedPage = $pageNumber;
        ProductDebug::getInstance()->setProductIds(
            CategoryMerchandising::parseProductIds($this->lastResult)
        );
        $this->logger->debugCmp(
            sprintf(
                'Got %d / %d (total) product ids from Nosto CMP for category "%s", using page num: %d, using limit: %d',
                $this->lastResult->getResultSet()->count(),
                $this->lastResult->getTotalPrimaryCount(),
                $category,
                $pageNumber,
                $limit
            ),
            $this
        );
        return $this->lastResult;
    }

    /**
     * @inheritDoc
     */
    public function getLastResult(): ?CategoryMerchandisingResult
    {
        return $this->lastResult;
    }

    /**
     * Get the current category
     * @param StoreInterface $store
     * @return null|string
     */
    private function getCurrentCategoryString(StoreInterface $store)
    {
        /** @noinspection PhpDeprecationInspection */
        $category = $this->registry->registry('current_category'); //@phan-suppress-current-line PhanDeprecatedFunction
        return $this->categoryBuilder->getCategory($category, $store);
    }

    /**
     * @param $id
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function setCategoryInRegistry($id): void
    {
        $store = $this->storeManager->getStore();
        $category = $this->categoryRepository->get($id, $store->getId());
        $this->registry->register('current_category', $category);
    }

    /**
     * @return int
     */
    public function getLastUsedLimit(): int
    {
        return $this->lastUsedLimit;
    }

    /**
     * @return int
     */
    public function getLastUsedPage(): int
    {
        return $this->lastUsedPage;
    }
}
