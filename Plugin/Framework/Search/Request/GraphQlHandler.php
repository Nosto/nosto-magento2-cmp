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

namespace Nosto\Cmp\Plugin\Framework\Search\Request;

use Magento\Store\Model\Store;
use Nosto\Cmp\Exception\GraphqlModelException;
use Nosto\Cmp\Helper\Data as CmpHelperData;
use Nosto\Cmp\Helper\SearchEngine;
use Nosto\Cmp\Model\Service\Facet\BuildGraphQlFacetService;
use Nosto\Cmp\Model\Service\Recommendation\SessionService;
use Nosto\Cmp\Model\Service\Recommendation\StateAwareCategoryServiceInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger;

class GraphQlHandler extends AbstractHandler
{

    /** @var BuildGraphQlFacetService */
    private $buildFacetService;

    /** @var SessionService */
    private $sessionService;

    /** @var int */
    private $pageSize;

    /**
     * GraphQlHandler constructor.
     * @param BuildGraphQlFacetService $buildFacetService
     * @param SearchEngine $searchEngineHelper
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param CmpHelperData $cmpHelperData
     * @param StateAwareCategoryServiceInterface $categoryService
     * @param SessionService $sessionService
     * @param Logger $logger
     * @param int $pageSize
     */
    public function __construct(
        BuildGraphQlFacetService $buildFacetService,
        SearchEngine $searchEngineHelper,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        CmpHelperData $cmpHelperData,
        StateAwareCategoryServiceInterface $categoryService,
        SessionService $sessionService,
        Logger $logger,
        $pageSize
    ) {
        parent::__construct(
            $searchEngineHelper,
            $nostoHelperAccount,
            $nostoHelperScope,
            $cmpHelperData,
            $categoryService,
            $logger
        );
        $this->buildFacetService = $buildFacetService;
        $this->sessionService = $sessionService;
        $this->pageSize = $pageSize;
    }

    /**
     * @inheritDoc
     */
    protected function getBindKey()
    {
        return self::KEY_BIND_TO_GRAPHQL;
    }

    /**
     * There's no way in StateAwareCategoryService to get the category
     * when the request comes from GraphQl
     *
     * @inheritDoc
     */
    protected function preFetchOps(array $requestData)
    {
        $this->categoryService->setCategoryInRegistry(
            $requestData[self::KEY_FILTERS][self::KEY_CATEGORY_FILTER][self::KEY_VALUE]
        );
    }

    /**
     * @inheritDoc
     * @throws GraphqlModelException
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function parseLimit(Store $store, array $requestData)
    {
        if ($this->pageSize != -1) {
            $this->trace('Using DI value (%s) for the page size', [$this->pageSize]);
            return $this->pageSize;
        }

        //Get limit/pageSize from session if session exists
        $model = $this->sessionService->getGraphqlModel();
        if ($model != null) {
            return $model->getLimit();
        } else {
            throw new GraphqlModelException($store);
        }
    }

    /**
     * @inheritDoc
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function getFilters(Store $store, array $requestData)
    {
        return $this->buildFacetService->getFacets($requestData);
    }

    /**
     * @inheritDoc
     * @throws GraphqlModelException
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function parsePageNumber(Store $store, array $requestData)
    {
        //Get limit/pageSize from session if session exists
        $model = $this->sessionService->getGraphqlModel();
        if ($model != null) {
            return $model->getCurrentPage() - 1;
        } else {
            throw new GraphqlModelException($store);
        }
    }
}
