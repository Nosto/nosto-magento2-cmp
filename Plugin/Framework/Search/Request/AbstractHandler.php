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

use Magento\Framework\Search\Request\Cleaner;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Cmp\Helper\SearchEngine;
use Nosto\Cmp\Logger\LoggerInterface;
use Nosto\Cmp\Model\Filter\WebFilters;
use Nosto\Cmp\Model\Filter\FiltersInterface;
use Nosto\Cmp\Model\Service\Recommendation\StateAwareCategoryServiceInterface;
use Nosto\Cmp\Plugin\Catalog\Block\ParameterResolverInterface;
use Nosto\Cmp\Utils\CategoryMerchandising;
use Nosto\Cmp\Utils\Search;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;

abstract class AbstractHandler
{
    const KEY_ES_PRODUCT_ID = '_id';
    const KEY_MYSQL_PRODUCT_ID = 'entity_id';
    const KEY_BIND_TO_QUERY = 'catalog_view_container';
    const KEY_BIND_TO_GRAPHQL = 'graphql_product_search';
    const KEY_CATEGORY_FILTER = 'category_filter';
    const KEY_QUERIES = 'queries';
    const KEY_FILTERS = 'filters';
    const KEY_VALUE = 'value';
    const KEY_CMP = 'nosto_cmp_id_search';
    const KEY_RESULTS_FROM = 'from';
    const KEY_RESULT_SIZE = 'size';

    public static $nostoTmpSort = [5, 11, 401, 2023, 1];

    /**
     * @var ParameterResolverInterface
     */
    private $parameterResolver;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SearchEngine
     */
    private $searchEngineHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var NostoHelperAccount
     */
    private $accountHelper;

    /**
     * @var StateAwareCategoryServiceInterface
     */
    protected $categoryService;

    /**
     * @param ParameterResolverInterface $parameterResolver
     * @param SearchEngine $searchEngineHelper
     * @param StoreManagerInterface $storeManager
     * @param NostoHelperAccount $nostoHelperAccount
     * @param StateAwareCategoryServiceInterface $categoryService
     * @param LoggerInterface $logger
     */
    public function __construct(
        ParameterResolverInterface $parameterResolver,
        SearchEngine $searchEngineHelper,
        StoreManagerInterface $storeManager,
        NostoHelperAccount $nostoHelperAccount,
        StateAwareCategoryServiceInterface $categoryService,
        LoggerInterface $logger
    ) {
        $this->parameterResolver = $parameterResolver;
        $this->logger = $logger;
        $this->searchEngineHelper = $searchEngineHelper;
        $this->storeManager = $storeManager;
        $this->accountHelper = $nostoHelperAccount;
        $this->categoryService = $categoryService;
    }

    /**
     * @param array $requestData
     * @return array
     */
    public function handle(array $requestData)
    {
        $this->logger->debugCmp(
            sprintf(
                'Using %s as search engine',
                $this->searchEngineHelper->getCurrentEngine()
            ),
            $this
        );

        $this->preFetchOps($requestData);

        $productIds = $this->getCmpProductIds(
            $this->parsePageNumber($requestData),
            $this->parseLimit($requestData)
        );
        $this->cleanUpCmpSort($requestData);
        if (empty($productIds)) {
            $this->logger->debugCmp(
                'Nosto did not return products for the request',
                $this,
                $requestData
            );
            return $requestData;
        }
        $this->resetRequestData($requestData);
        $this->applyCmpFilter(
            $requestData,
            $productIds
        );
        return $requestData;
    }

    /**
     * @return string
     */
    abstract function getBindKey();

    /**
     * @param array $requestData
     * @return void
     */
    abstract function preFetchOps(array $requestData);

    /**
     * @return FiltersInterface
     */
    abstract function getFilters();

    /**
     * Removes the Nosto sorting key as it's not indexed
     *
     * @param array $requestData
     */
    private function cleanUpCmpSort(array &$requestData)
    {
        unset($requestData['sort'][Search::findNostoSortingIndex($requestData)]);
    }

    /**
     * Applies given product ids to the query & filters
     *
     * @param array $requestData
     * @param array $productIds
     */
    private function applyCmpFilter(array &$requestData, array $productIds)
    {
        $bindKey = $this->getBindKey();

        $requestData[self::KEY_QUERIES][$bindKey]['queryReference'][] = [
            'clause' => 'must',
            'ref' => 'nosto_cmp_id_search'
        ];

        $requestData[self::KEY_QUERIES][self::KEY_CMP] = [
            'name' => 'nosto_cmp',
            'filterReference' => [
                [
                    'clause' => 'must',
                    'ref' => 'prod_id',
                ],
            ],
            'type' => 'filteredQuery',
        ];
        $requestData['filters']['prod_id'] = [
            'name' => 'prod_id',
            'filterReference' => [
                [
                    'clause' => 'must',
                    'ref' => 'prod_ids',
                ],
            ],
            'type' => 'boolFilter',
        ];
        $requestData['filters']['prod_ids'] = [
            'name' => 'prod_ids',
            'field' => $this->getProductIdField(),
            'type' => 'termFilter',
            'value' => $productIds
        ];
    }

    /**
     * @param array $requestData
     * @return int
     */
    private function parsePageNumber(array $requestData)
    {
        $from = $requestData[self::KEY_RESULTS_FROM];
        if ($from < 1) {
            return 0;
        }
        return (int) ceil($from / $this->parseLimit($requestData));
    }

    /**
     * @param array $requestData
     * @return int
     */
    abstract function parseLimit(array $requestData);

    /**
     * @param int $pageNum
     * @param int $limit
     * @return array|null
     */
    private function getCmpProductIds($pageNum, $limit)
    {
        try {
            $res = $this->categoryService->getPersonalisationResult(
                $this->getFilters(),
                $pageNum,
                $limit
            );
            return $res ? CategoryMerchandising::parseProductIds($res) : null;
        } catch (\Exception $e) {
            $this->logger->exception($e);
            return null;
        }
    }

    /**
     * Removes queries & filters from the request data
     *
     * @param array $requestData
     */
    private function resetRequestData(array &$requestData)
    {
        $removedQueries = [];
        foreach ($requestData[self::KEY_QUERIES] as $key => $definition) {
            if ($key !== self::KEY_BIND_TO_QUERY && $key !== self::KEY_BIND_TO_GRAPHQL) {
                $removedQueries[$key] = $key;
                unset($requestData[self::KEY_QUERIES][$key]);
            }
        }
        $removedRefs = [];
        $bindKey = $this->getBindKey();

        // Also referencing definitions
        foreach ($requestData[self::KEY_QUERIES][$bindKey]['queryReference'] as $refIndex => $ref) {
            $refStr = $ref['ref'];
            if (isset($removedQueries[$refStr])) {
                $removedRefs[$refStr] = $refStr;
                unset($requestData[self::KEY_QUERIES][$bindKey]['queryReference'][$refIndex]);
            }
        }
        $requestData['filters'] = [];

        // Reset also the start point since Nosto will only use product ids
        $requestData[self::KEY_RESULTS_FROM] = 0;
    }

    /**
     * Return the product id field
     *
     * @return string
     */
    private function getProductIdField()
    {
        if ($this->searchEngineHelper->isMysql()) {
            return self::KEY_MYSQL_PRODUCT_ID;
        } else {
            return self::KEY_ES_PRODUCT_ID;
        }
    }
}
