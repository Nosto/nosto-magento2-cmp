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

namespace Nosto\Cmp\Model\Service\Merchandise;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Nosto\Cmp\Exception\MissingAccountException;
use Nosto\Cmp\Exception\MissingTokenException;
use Nosto\Cmp\Exception\SessionCreationException;
use Magento\Store\Model\Store;
use Nosto\Cmp\Helper\Data;
use Nosto\Cmp\Model\Facet\FacetInterface;
use Nosto\Cmp\Model\Merchandise\MerchandiseRequestParams;
use Nosto\Cmp\Model\Service\VisitSession\SessionService as VisitSessionService;
use Nosto\Cmp\Utils\Traits\LoggerTrait;
use Nosto\Request\Api\Token;
use Nosto\Service\FeatureAccess;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Customer\Customer as NostoCustomer;
use Nosto\Tagging\Model\Service\Product\Category\DefaultCategoryService as CategoryBuilder;
use Nosto\Cmp\Model\Service\MagentoSession\SessionService as ResultSessionService;

class RequestParamsService
{
    const NOSTO_PREVIEW_COOKIE = 'nostopreview';

    use LoggerTrait {
        LoggerTrait::__construct as loggerTraitConstruct; // @codingStandardsIgnoreLine
    }

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
     * @var CategoryRepositoryInterface
     */
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
     * @var VisitSessionService
     */
    private $visitSessionService;

    /**
     * @var ResultSessionService
     */
    private $resultSessionService;

    /**
     * @param CookieManagerInterface $cookieManager
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param Registry $registry
     * @param CategoryBuilder $categoryBuilder
     * @param Logger $logger
     * @param Data $nostoCmpHelper
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ManagerInterface $eventManager
     * @param VisitSessionService $visitSessionService
     * @param ResultSessionService $resultSessionService
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        Registry $registry,
        CategoryBuilder $categoryBuilder,
        Logger $logger,
        Data $nostoCmpHelper,
        CategoryRepositoryInterface $categoryRepository,
        ManagerInterface $eventManager,
        VisitSessionService $visitSessionService,
        ResultSessionService $resultSessionService
    ) {
        $this->loggerTraitConstruct(
            $logger
        );
        $this->cookieManager = $cookieManager;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->registry = $registry;
        $this->categoryBuilder = $categoryBuilder;
        $this->nostoCmpHelper = $nostoCmpHelper;
        $this->categoryRepository = $categoryRepository;
        $this->eventManager = $eventManager;
        $this->visitSessionService = $visitSessionService;
        $this->resultSessionService = $resultSessionService;
    }

    /**
     * @param FacetInterface $facets
     * @param $pageNumber
     * @param $limit
     * @return MerchandiseRequestParams
     * @throws MissingAccountException
     * @throws MissingTokenException
     * @throws SessionCreationException
     */
    public function createRequestParams(FacetInterface $facets, $pageNumber, $limit)
    {
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
            $customerId = $this->visitSessionService->getNewNostoSession($store, $nostoAccount);
        }

        $limit = $this->sanitizeLimit($store, $limit);
        $category = $this->getCurrentCategoryString($store);

        $previewMode = (bool)$this->cookieManager->getCookie(self::NOSTO_PREVIEW_COOKIE);

        //Get batch token
        $token = '';
        $batchModel = $this->resultSessionService->getBatchModel();
        if ($batchModel != null
            && ($batchModel->getLastUsedLimit() == $limit)
            && ($batchModel->getLastFetchedPage() == $pageNumber - 1)) {
            $token = $batchModel->getBatchToken();
        }

        return new MerchandiseRequestParams(
            $nostoAccount,
            $facets,
            $customerId,
            $category,
            $pageNumber,
            $limit,
            $previewMode,
            $token
        );
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
     * @param Store $store
     * @param int $limit
     * @return int
     */
    private function sanitizeLimit(Store $store, int $limit)
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
