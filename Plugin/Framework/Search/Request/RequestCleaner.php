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
use Magento\TestFramework\Store\StoreManager;
use Nosto\Cmp\Helper\SearchEngine;
use Nosto\Cmp\Model\Filter\FilterBuilder;
use Nosto\Cmp\Plugin\Catalog\Block\ParameterResolverInterface;
use Nosto\Cmp\Utils\Debug\ServerTiming;
use Nosto\Cmp\Utils\Search;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Customer\Customer as NostoCustomer;

class RequestCleaner
{
    const KEY_ES_PRODUCT_ID = '_id';
    const KEY_MYSQL_PRODUCT_ID = 'entity_id';
    const KEY_BIND_TO_QUERY = 'catalog_view_container';
    const KEY_QUERIES = 'queries';
    const KEY_FILTERS = 'filters';
    const KEY_CMP = 'nosto_cmp_id_search';

    /**
     * @var ParameterResolverInterface
     */
    private $parameterResolver;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var SearchEngine
     */
    private $searchEngineHelper;

    /**
     * @var StoreManager
     */
    private $storeManager;

    /**
     * @var NostoHelperAccount
     */
    private $accountHelper;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @param ParameterResolverInterface $parameterResolver
     * @param SearchEngine $searchEngineHelper
     * @param StoreManagerInterface $storeManager
     * @param NostoHelperAccount $nostoHelperAccount
     * @param FilterBuilder $filterBuilder
     * @param Logger $logger
     */
    public function __construct(
        ParameterResolverInterface $parameterResolver,
        SearchEngine $searchEngineHelper,
        StoreManagerInterface $storeManager,
        NostoHelperAccount $nostoHelperAccount,
        FilterBuilder $filterBuilder,
        Logger $logger
    ) {
        $this->parameterResolver = $parameterResolver;
        $this->logger = $logger;
        $this->searchEngineHelper = $searchEngineHelper;
        $this->storeManager = $storeManager;
        $this->accountHelper = $nostoHelperAccount;
        $this->filterBuilder = $filterBuilder;
    }

    /**
     * Cleans non persisted sorting parameters etc. for the request data
     *
     * @param Cleaner $cleaner
     * @param array $requestData
     * @return array
     */
    public function afterClean(Cleaner $cleaner, array $requestData)
    {
        if (!Search::isNostoSorting($requestData)) {
            return $requestData;
        }
        try {
            if (!isset($requestData[self::KEY_QUERIES][self::KEY_BIND_TO_QUERY])
                || !isset($requestData[self::KEY_QUERIES][self::KEY_BIND_TO_QUERY]['queryReference'])
            ) {
                $this->logger->debugWithSource(
                    sprintf(
                        'Could not find %s from ES request data',
                        self::KEY_BIND_TO_QUERY
                    ),
                    $requestData,
                    $this
                );
                return $requestData;
            }
            $origData = $requestData; //TODO - remove me, only for debugging purposes
            $this->resetRequestData($requestData);
            $this->applyCmpFilter($requestData, $this->getCmpProductIds());
            $this->applyCmpSort($requestData, $this->getCmpProductIds());
        } catch (\Exception $e) {
            $this->logger->debugWithSource(
                'Failed to apply CMP - see exception log(s) for datails',
                $requestData,
                $this
            );
            $this->logger->exception($e);
        } finally {
            return $requestData;
        }
    }


    /**
     * Removes the Nosto sorting key as it's not indexed
     *
     * @param array $requestData
     * @param $productIds
     */
    private function applyCmpSort(array &$requestData, $productIds)
    {
        unset($requestData['sort'][Search::findNostoSortingIndex($requestData)]);

        //TODO: check if we can use script for sorting the results
    }

    /**
     * Applies given product ids to the query & filters
     *
     * @param array $requestData
     * @param array $productIds
     */
    private function applyCmpFilter(array &$requestData, array $productIds)
    {
        $requestData[self::KEY_QUERIES][self::KEY_BIND_TO_QUERY]['queryReference'][] = [
            'clause' =>'must',
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
     * @return int[]
     */
    private function getCmpProductIds()
    {
        // TODO
        // - inject CMP operation
        // - build the filters
        // - fetch the product ids from Nosto
        return [5, 11, 401, 2023, 1];
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
            if ($key !== self::KEY_BIND_TO_QUERY) {
                $removedQueries[$key] = $key;
                unset($requestData[self::KEY_QUERIES][$key]);
            }
        }
        $removedRefs = [];
        // Also referencing definitions
        foreach ($requestData[self::KEY_QUERIES][self::KEY_BIND_TO_QUERY]['queryReference'] as $refIndex => $ref) {
            $refStr = $ref['ref'];
            if (isset($removedQueries[$refStr])) {
                $removedRefs[$refStr] = $refStr;
                unset($requestData[self::KEY_QUERIES][self::KEY_BIND_TO_QUERY]['queryReference'][$refIndex]);
            }
        }
        $requestData['filters'] = [];
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