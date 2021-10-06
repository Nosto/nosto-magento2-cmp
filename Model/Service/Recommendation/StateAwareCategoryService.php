<?php /** @noinspection PhpDeprecationInspection */

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

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Cmp\Exception\MissingCookieException;
use Nosto\Cmp\Helper\Data;
use Nosto\Cmp\Logger\LoggerInterface;
use Nosto\Cmp\Model\Facet\FacetInterface;
use Nosto\Cmp\Model\Service\Session\SessionService;
use Nosto\Cmp\Utils\CategoryMerchandising as CategoryMerchandisingUtil;
use Nosto\Cmp\Utils\Debug\ServerTiming;
use Nosto\NostoException;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Service\FeatureAccess;
use Nosto\Tagging\Helper\Account;
use Nosto\Tagging\Model\Customer\Customer as NostoCustomer;
use Nosto\Tagging\Model\Service\Product\Category\DefaultCategoryService as CategoryBuilder;

class StateAwareCategoryService implements StateAwareCategoryServiceInterface
{
    const NOSTO_PREVIEW_COOKIE = 'nostopreview';
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

    /** @var CategoryRepositoryInterface */
    private $categoryRepository;

    /**
     * @var Data
     */
    private $nostoCmpHelper;

    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * @var SessionService
     */
    private $nostoSessionService;

    /**
     * StateAwareCategoryService constructor.
     * @param CookieManagerInterface $cookieManager
     * @param Category $categoryService
     * @param Account $nostoHelperAccount
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     * @param CategoryBuilder $categoryBuilder
     * @param LoggerInterface $logger
     * @param Data $nostoCmpHelper
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ManagerInterface $eventManager
     * @param SessionService $sessionService
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        Category $categoryService,
        Account $nostoHelperAccount,
        StoreManagerInterface $storeManager,
        Registry $registry,
        CategoryBuilder $categoryBuilder,
        LoggerInterface $logger,
        Data $nostoCmpHelper,
        CategoryRepositoryInterface $categoryRepository,
        ManagerInterface $eventManager,
        SessionService $sessionService
    ) {
        $this->cookieManager = $cookieManager;
        $this->categoryService = $categoryService;
        $this->cookieManager = $cookieManager;
        $this->accountHelper = $nostoHelperAccount;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->registry = $registry;
        $this->categoryBuilder = $categoryBuilder;
        $this->nostoCmpHelper = $nostoCmpHelper;
        $this->categoryRepository = $categoryRepository;
        $this->eventManager = $eventManager;
        $this->nostoSessionService = $sessionService;
    }

    /**
     * @inheritDoc
     * @throws NostoException
     * @throws LocalizedException
     * @throws MissingCookieException
     */
    public function getPersonalisationResult(
        FacetInterface $facets,
        $pageNumber,
        $limit
    ): ?CategoryMerchandisingResult {
        $store = $this->storeManager->getStore();
        //@phan-suppress-next-next-line PhanTypeMismatchArgument
        /** @noinspection PhpParamsInspection */
        $nostoAccount = $this->accountHelper->findAccount($store);
        if ($nostoAccount === null) {
            throw new NostoException('Account cannot be null');
        }
        $customerId = $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME);
        //Create new session which Nosto won't track
        if ($customerId === null) {
            $customerId = $this->nostoSessionService->getNewNostoSession($nostoAccount);
        }

        $limit = $this->sanitizeLimit($store, $limit);
        $category = $this->getCurrentCategoryString($store);
        $featureAccess = new FeatureAccess($nostoAccount);
        if (!$featureAccess->canUseGraphql()) {
            throw new NostoException('Missing Nosto API_APPS token');
        }

        $previewMode = (bool)$this->cookieManager->getCookie(self::NOSTO_PREVIEW_COOKIE);
        $this->lastResult = ServerTiming::getInstance()->instrument(
            function () use ($nostoAccount, $previewMode, $category, $pageNumber, $limit, $facets, $customerId) {
                return $this->categoryService->getPersonalisationResult(
                    $nostoAccount,
                    $facets,
                    $customerId,
                    $category,
                    $pageNumber,
                    $limit,
                    $previewMode
                );
            },
            self::TIME_PROF_GRAPHQL_QUERY
        );
        $this->lastUsedLimit = $limit;

        $this->eventManager->dispatch(
            CategoryMerchandisingUtil::DISPATCH_EVENT_NAME_POST_RESULTS,
            [
                CategoryMerchandisingUtil::DISPATCH_EVENT_KEY_LIMIT => $limit,
                CategoryMerchandisingUtil::DISPATCH_EVENT_KEY_PAGE => $pageNumber,
            ]
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
     * @throws NoSuchEntityException
     */
    public function setCategoryInRegistry($id): void
    {
        $store = $this->storeManager->getStore();
        $category = $this->categoryRepository->get($id, $store->getId());
        /** @noinspection PhpDeprecationInspection */
        $this->registry->register('current_category', $category); //@phan-suppress-current-line PhanDeprecatedFunction
    }

    /**
     * @inheritDoc
     */
    public function getLastUsedLimit(): int
    {
        return $this->lastUsedLimit;
    }

    /**
     * @param StoreInterface $store
     * @param int $limit
     * @return int
     */
    private function sanitizeLimit(StoreInterface $store, $limit)
    {
        $maxLimit = $this->nostoCmpHelper->getMaxProductLimit($store);
        if (!is_numeric($limit)
            || $limit > $maxLimit
            || $limit === 0
        ) {
            $this->logger->debugCmp(
                sprintf(
                    'Limit set to %d - original limit was %s',
                    $maxLimit,
                    $limit
                ),
                $this
            );
            return $maxLimit;
        }
        return $limit;
    }
}
