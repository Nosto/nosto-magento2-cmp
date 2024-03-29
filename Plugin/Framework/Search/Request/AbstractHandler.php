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

use Exception;
use Magento\Store\Model\Store;
use Nosto\Cmp\Exception\CmpException;
use Nosto\Cmp\Exception\MissingAccountException;
use Nosto\Cmp\Exception\MissingTokenException;
use Nosto\Cmp\Exception\SessionCreationException;
use Nosto\Cmp\Helper\Data as CmpHelperData;
use Nosto\Cmp\Helper\SearchEngine;
use Nosto\Cmp\Model\Facet\FacetInterface;
use Nosto\Cmp\Model\Service\Merchandise\MerchandiseServiceInterface;
use Nosto\Cmp\Model\Service\Merchandise\RequestParamsService;
use Nosto\Cmp\Utils\Request as RequestUtils;
use Nosto\Cmp\Utils\Search;
use Nosto\Cmp\Utils\Traits\LoggerTrait;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger;

abstract class AbstractHandler
{
    const KEY_ES_PRODUCT_ID = '_id';
    const KEY_BIND_TO_QUERY = 'catalog_view_container';
    const KEY_BIND_TO_GRAPHQL = 'graphql_product_search';
    const KEY_CATEGORY_FILTER = 'category_filter';
    const KEY_QUERIES = 'queries';
    const KEY_FILTERS = 'filters';
    const KEY_VALUE = 'value';
    const KEY_RESULTS_FROM = 'from';
    const KEY_RESULT_SIZE = 'size';

    use LoggerTrait {
        LoggerTrait::__construct as loggerTraitConstruct; // @codingStandardsIgnoreLine
    }

    /**
     * @var SearchEngine
     */
    private SearchEngine $searchEngineHelper;

    /**
     * @var NostoHelperAccount
     */
    private NostoHelperAccount $accountHelper;

    /**
     * @var CmpHelperData
     */
    private CmpHelperData $cmpHelperData;

    /**
     * @var NostoHelperScope
     */
    protected NostoHelperScope $nostoHelperScope;

    /**
     * @var MerchandiseServiceInterface
     */
    protected MerchandiseServiceInterface $merchandiseService;

    /**
     * @var RequestParamsService
     */
    private RequestParamsService $requestParamService;

    /**
     * @param SearchEngine $searchEngineHelper
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param CmpHelperData $cmpHelperData
     * @param MerchandiseServiceInterface $merchandiseService
     * @param RequestParamsService $requestParamsService
     * @param Logger $logger
     */
    public function __construct(
        SearchEngine               $searchEngineHelper,
        NostoHelperAccount         $nostoHelperAccount,
        NostoHelperScope           $nostoHelperScope,
        CmpHelperData              $cmpHelperData,
        MerchandiseServiceInterface $merchandiseService,
        RequestParamsService       $requestParamsService,
        Logger                     $logger
    ) {
        $this->loggerTraitConstruct(
            $logger
        );
        $this->searchEngineHelper = $searchEngineHelper;
        $this->accountHelper = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->cmpHelperData = $cmpHelperData;
        $this->merchandiseService = $merchandiseService;
        $this->requestParamService = $requestParamsService;
    }

    /**
     * @param array $requestData
     * @return void
     */
    public function handle(array &$requestData)
    {
        $this->trace('Using %s as search engine', [$this->searchEngineHelper->getCurrentEngine()]);
        $this->preFetchOps($requestData);
        Search::cleanUpCmpSort($requestData);

        $storeId = $this->getStoreId($requestData);
        $store = $this->nostoHelperScope->getStore($storeId);

        try {
            $limit = $this->parseLimit($store, $requestData);
            $productIds = $this->getCmpProductIds(
                $this->getFilters($store, $requestData),
                $this->parsePageNumber($store, $requestData),
                $limit
            );
            //In case CM category is not configured in nosto
            if ($productIds == null || empty($productIds)) {
                $this->trace('Nosto did not return products for the request', [], $requestData);
                $this->setFallbackSort($store, $requestData);
                return;
            }
        } catch (CmpException $e) {
            $this->exception($e);
            $this->setFallbackSort($store, $requestData);
            return;
        } catch (Exception $e) {
            $this->exception($e);
            $this->setFallbackSort($store, $requestData);
            return;
        }
        //Add CM sorting to the RequestData array
        $this->applyCmpFilter(
            $requestData,
            $productIds,
            $limit
        );
    }

    /**
     * @return string
     */
    abstract protected function getBindKey();

    /**
     * @param array $requestData
     * @return void
     */
    abstract protected function preFetchOps(array $requestData);

    /**
     * @param Store $store
     * @param array $requestData
     * @return FacetInterface
     */
    abstract protected function getFilters(Store $store, array $requestData);

    /**
     * Set fallback sort order
     *
     * @param Store $store
     * @param array $requestData
     */
    private function setFallbackSort(Store $store, array &$requestData)
    {
        $sorting = $this->cmpHelperData->getFallbackSorting($store);
        $requestData['sort'][] = [
            'field' => $sorting,
            'direction' => 'ASC'
        ];
    }

    /**
     * Applies given product ids to the query & filters
     *
     * @param array $requestData
     * @param array $productIds
     * @param int $limit
     */
    private function applyCmpFilter(array &$requestData, array $productIds, int $limit)
    {
        $bindKey = $this->getBindKey();

        if ($this->searchEngineHelper->isMysql()) {
            $this->logger->debugWithSource(
                'Nosto does not support Mysql search',
                $requestData,
                $this
            );
            return;
        }

        $requestData[self::KEY_QUERIES][$bindKey]['queryReference'][] = [
            'clause' => 'must',
            'ref' => RequestUtils::KEY_CMP
        ];

        $requestData[self::KEY_QUERIES][RequestUtils::KEY_CMP] = [
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
            'field' => self::KEY_ES_PRODUCT_ID,
            'type' => 'termFilter',
            'value' => $productIds
        ];
        $requestData['size'] = $limit;
    }

    /**
     * @param array $requestData
     * @return int|null
     */
    protected function getStoreId(array $requestData)
    {
        if (isset($requestData["dimensions"]["scope"]["value"])) {
            return (int) $requestData["dimensions"]["scope"]["value"];
        }
        return null;
    }

    /**
     * @param Store $store
     * @param array $requestData
     * @return int
     */
    abstract public function parsePageNumber(Store $store, array $requestData);

    /**
     * @param Store $store
     * @param array $requestData
     * @return int
     * @throws Exception
     */
    abstract public function parseLimit(Store $store, array $requestData);

    /**
     * @param FacetInterface $facet
     * @param $pageNum
     * @param $limit
     * @return array|null
     * @throws MissingAccountException
     * @throws MissingTokenException
     * @throws SessionCreationException
     */
    private function getCmpProductIds(FacetInterface $facet, $pageNum, $limit)
    {
        $requestParams = $this->requestParamService->createRequestParams($facet, $pageNum, $limit);
        $res = $this->merchandiseService->getMerchandiseResults($requestParams);
        return $res ? $res->parseProductIds() : null;
    }
}
