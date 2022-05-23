<?php /** @noinspection PhpUnusedParameterInspection */

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

namespace Nosto\Cmp\Plugin\CatalogGraphQl\Products\Query;

use Magento\CatalogGraphQl\Model\Resolver\Products\Query\Search as MagentoSearch;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResult;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResultFactory;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Nosto\Cmp\Helper\CategorySorting;
use Nosto\Cmp\Helper\Data as CmpHelperData;
use Nosto\Cmp\Model\Service\MagentoSession\GraphQlParamModel;
use Nosto\Cmp\Model\Service\MagentoSession\SessionService;

class Search
{
    const SORT_KEY = 'sort';
    const PAGE_SIZE_KEY = 'pageSize';
    const CURRENT_PAGE_KEY = 'currentPage';

    /** @var SearchResultFactory */
    private SearchResultFactory $searchResultFactory;

    /** @var SessionService */
    private SessionService $sessionService;

    /** @var CmpHelperData */
    private CmpHelperData $cmpHelperData;

    /**
     * Search constructor.
     * @param SearchResultFactory $searchResultFactory
     * @param SessionService $sessionService
     * @param CmpHelperData $cmpHelperData
     */
    public function __construct(
        SearchResultFactory $searchResultFactory,
        SessionService $sessionService,
        CmpHelperData $cmpHelperData
    ) {
        $this->searchResultFactory =  $searchResultFactory;
        $this->sessionService = $sessionService;
        $this->cmpHelperData = $cmpHelperData;
    }

    /**
     * @param MagentoSearch $search
     * @param array $args
     * @param ResolveInfo $info
     * @param ContextInterface $context
     * @return array
     * @noinspection PhpUnusedParameterInspection
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function beforeGetResult(MagentoSearch $search, array $args, ResolveInfo $info, ContextInterface $context)
    {
        if (isset($args[self::SORT_KEY]) && isset($args[self::SORT_KEY][CategorySorting::NOSTO_PERSONALIZED_KEY])) {
            $pageSize = $args[self::PAGE_SIZE_KEY];
            $currentPage = $args[self::CURRENT_PAGE_KEY];
            $this->sessionService->setGraphqlModel(new GraphQlParamModel($pageSize, $currentPage));

            $sorting = $this->cmpHelperData->getFallbackSorting();
            $args[self::SORT_KEY][$sorting] = 'ASC';
        }
        return [$args, $info, $context];
    }

    /**
     * @param MagentoSearch $search
     * @param SearchResult $searchResult
     * @param array $args
     * @return SearchResult
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function afterGetResult(
        MagentoSearch $search,
        SearchResult  $searchResult,
        array $args
    ) {
        if (isset($args[self::SORT_KEY]) && isset($args[self::SORT_KEY][CategorySorting::NOSTO_PERSONALIZED_KEY])
            && $this->getTotalPages() != 0) {
            return $this->searchResultFactory->create([
                'totalCount' => $this->getTotalCount(),
                'productsSearchResult' => $searchResult->getProductsSearchResult(),
                'searchAggregation' => $searchResult->getSearchAggregation(),
                'pageSize' => $searchResult->getPageSize(),
                'currentPage' => $searchResult->getCurrentPage(),
                'totalPages' => $this->getTotalPages(),
            ]);
        }
        return $searchResult;
    }

    /**
     * Returns 0 when there are no results, also includes the case CM is not enabled for a category
     * @return int
     */
    private function getTotalPages()
    {
        $batchModel = $this->sessionService->getBatchModel();
        if ($batchModel === null) {
            return 0;
        }
        return (int) ceil($batchModel->getTotalCount() / $batchModel->getLastUsedLimit());
    }

    /**
     * @return int
     */
    private function getTotalCount()
    {
        $batchModel = $this->sessionService->getBatchModel();
        if ($batchModel === null) {
            return 0;
        }
        return $batchModel->getTotalCount();
    }
}
