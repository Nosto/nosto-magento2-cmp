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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Nosto\Cmp\Exception\MissingAccountException;
use Nosto\Cmp\Exception\MissingTokenException;
use Nosto\Cmp\Exception\SessionCreationException;
use Magento\Store\Model\Store;
use Nosto\Cmp\Helper\Data;
use Nosto\Cmp\Model\Facet\FacetInterface;
use Nosto\Cmp\Model\Service\Session\SessionService;
use Nosto\Cmp\Observer\App\Action\PostRequestAction;
use Nosto\Cmp\Utils\Debug\ServerTiming;
use Nosto\Cmp\Utils\Traits\LoggerTrait;
use Nosto\Request\Api\Token;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Service\FeatureAccess;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Customer\Customer as NostoCustomer;
use Nosto\Tagging\Model\Service\Product\Category\DefaultCategoryService as CategoryBuilder;

class StateAwareCategoryService implements StateAwareCategoryServiceInterface
{
    const NOSTO_PREVIEW_COOKIE = 'nostopreview';
    const TIME_PROF_GRAPHQL_QUERY = 'cmp_graphql_query';

    use LoggerTrait {
        LoggerTrait::__construct as loggerTraitConstruct; // @codingStandardsIgnoreLine
    }

    /**
     * @var Category
     */
    private $categoryService;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var NostoHelperAccount
     */
    private $nostoHelperAccount;

    /**
     * @var NostoHelperScope
     */
    private $nostoHelperScope;

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
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param Registry $registry
     * @param CategoryBuilder $categoryBuilder
     * @param Logger $logger
     * @param Data $nostoCmpHelper
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ManagerInterface $eventManager
     * @param SessionService $sessionService
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        Category $categoryService,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        Registry $registry,
        CategoryBuilder $categoryBuilder,
        Logger $logger,
        Data $nostoCmpHelper,
        CategoryRepositoryInterface $categoryRepository,
        ManagerInterface $eventManager,
        SessionService $sessionService
    ) {
        $this->loggerTraitConstruct(
            $logger
        );
        $this->cookieManager = $cookieManager;
        $this->categoryService = $categoryService;
        $this->cookieManager = $cookieManager;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->registry = $registry;
        $this->categoryBuilder = $categoryBuilder;
        $this->nostoCmpHelper = $nostoCmpHelper;
        $this->categoryRepository = $categoryRepository;
        $this->eventManager = $eventManager;
        $this->nostoSessionService = $sessionService;
    }

    /**
     * @inheritDoc
     * @throws MissingAccountException
     * @throws MissingTokenException
     * @throws SessionCreationException
     */
    public function getPersonalisationResult(
        FacetInterface $facets,
        $pageNumber,
        $limit
    ): ?CategoryMerchandisingResult {
        // Current store id value is unavailable
        $store = $this->nostoHelperScope->getStore();
        $nostoAccount = $this->nostoHelperAccount->findAccount($store);

        // Can happen when CM is implemented through GraphQL
        if ($nostoAccount === null) {
            throw new MissingAccountException($store);
        }

        // Can happen when CM is implemented through GraphQL
        $featureAccess = new FeatureAccess($nostoAccount);
        if (!$featureAccess->canUseGraphql()) {
            throw new MissingTokenException($store, Token::API_GRAPHQL);
        }

        $customerId = $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME);
        //Create new session which Nosto won't track
        if ($customerId === null) {
            $customerId = $this->nostoSessionService->getNewNostoSession($store, $nostoAccount);
        }

        $limit = $this->sanitizeLimit($store, $limit);
        $category = $this->getCurrentCategoryString($store);

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
            PostRequestAction::DISPATCH_EVENT_NAME_POST_RESULTS,
            [
                PostRequestAction::DISPATCH_EVENT_KEY_LIMIT => $limit,
                PostRequestAction::DISPATCH_EVENT_KEY_PAGE => $pageNumber,
            ]
        );

        $this->trace(
            'Got %d / %d (total) product ids from Nosto CMP for category "%s", using page num: %d, using limit: %d',
            [
                $this->lastResult->getResultSet()->count(),
                $this->lastResult->getTotalPrimaryCount(),
                $category,
                $pageNumber,
                $limit
            ]
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
     * @param Store $store
     * @return null|string
     */
    private function getCurrentCategoryString(Store $store)
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
        // Current store id value is unavailable
        $store = $this->nostoHelperScope->getStore();
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
     * @param Store $store
     * @param int $limit
     * @return int
     */
    private function sanitizeLimit(Store $store, $limit)
    {
        $maxLimit = $this->nostoCmpHelper->getMaxProductLimit($store);
        if (!is_numeric($limit)
            || $limit > $maxLimit
            || $limit === 0
        ) {
            $this->trace('Limit set to %d - original limit was %s', [$maxLimit, $limit]);
            return $maxLimit;
        }
        return $limit;
    }
}
