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

namespace Nosto\Cmp\Plugin\Framework\Search\Request;

use Exception;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Cmp\Helper\SearchEngine;
use Nosto\Cmp\Logger\LoggerInterface;
use Nosto\Cmp\Model\Filter\GraphQlFilters;
use Nosto\Cmp\Model\Service\Recommendation\SessionService;
use Nosto\Cmp\Model\Service\Recommendation\StateAwareCategoryServiceInterface;
use Nosto\Cmp\Plugin\Catalog\Block\ParameterResolverInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;

class GraphQlHandler extends AbstractHandler
{

    /** @var GraphQlFilters */
    private $filters;

    /** @var SessionService */
    private $sessionService;

    /**
     * GraphQlHandler constructor.
     * @param GraphQlFilters $filters
     * @param ParameterResolverInterface $parameterResolver
     * @param SearchEngine $searchEngineHelper
     * @param StoreManagerInterface $storeManager
     * @param NostoHelperAccount $nostoHelperAccount
     * @param StateAwareCategoryServiceInterface $categoryService
     * @param SessionService $sessionService
     * @param LoggerInterface $logger
     */
    public function __construct(
        GraphQlFilters $filters,
        ParameterResolverInterface $parameterResolver,
        SearchEngine $searchEngineHelper,
        StoreManagerInterface $storeManager,
        NostoHelperAccount $nostoHelperAccount,
        StateAwareCategoryServiceInterface $categoryService,
        SessionService $sessionService,
        LoggerInterface $logger
    ) {
        parent::__construct(
            $parameterResolver,
            $searchEngineHelper,
            $storeManager,
            $nostoHelperAccount,
            $categoryService,
            $logger
        );
        $this->filters = $filters;
        $this->sessionService = $sessionService;
    }

    /**
     * @inheritDoc
     */
    protected function getBindKey()
    {
        return self::KEY_BIND_TO_GRAPHQL;
    }

    /**
     * @inheritDoc
     */
    protected function preFetchOps(array $requestData)
    {
        $this->categoryService->setCategoryInRegistry(
            $requestData[self::KEY_FILTERS][self::KEY_CATEGORY_FILTER][self::KEY_VALUE]
        );
        $this->filters->setRequestData($requestData);
    }

    /**
     * @inheritDoc
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function parseLimit(array $requestData)
    {
        //Get limit/pageSize from session if session exists
        $model = $this->sessionService->getGraphqlModel();
        if ($model != null) {
            return $model->getLimit();
        } else {
            throw new \Exception("Could not get limit from session");
        }
    }

    /**
     * @inheritDoc
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @inheritDoc
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function parsePageNumber(array $requestData)
    {
        //Get limit/pageSize from session if session exists
        $model = $this->sessionService->getGraphqlModel();
        if ($model != null) {
            return $model->getCurrentPage() - 1;
        } else {
            throw new Exception("Could not get page size from session");
        }
    }
}
