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
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Cmp\Helper\Data as CmpHelperData;
use Nosto\Cmp\Helper\SearchEngine;
use Nosto\Cmp\Model\Facet\FacetInterface;
use Nosto\Cmp\Model\Service\Recommendation\StateAwareCategoryServiceInterface;
use Nosto\Cmp\Plugin\Catalog\Block\ParameterResolverInterface;
use Nosto\Cmp\Utils\CategoryMerchandising;
use Nosto\Cmp\Utils\Request as RequestUtils;
use Nosto\Cmp\Utils\Search;
use Nosto\Cmp\Utils\Traits\LoggerTrait;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Logger\Logger;

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
    const KEY_RESULTS_FROM = 'from';
    const KEY_RESULT_SIZE = 'size';

    use LoggerTrait {
        LoggerTrait::__construct as loggerTraitConstruct; // @codingStandardsIgnoreLine
    }

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
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var NostoHelperAccount
     */
    private $accountHelper;

    /**
     * @var CmpHelperData
     */
    private $cmpHelperData;

    /**
     * @var StateAwareCategoryServiceInterface
     */
    protected $categoryService;

    /**
     * AbstractHandler constructor.
     * @param ParameterResolverInterface $parameterResolver
     * @param SearchEngine $searchEngineHelper
     * @param StoreManagerInterface $storeManager
     * @param NostoHelperAccount $nostoHelperAccount
     * @param CmpHelperData $cmpHelperData
     * @param StateAwareCategoryServiceInterface $categoryService
     * @param Logger $logger
     */
    public function __construct(
        ParameterResolverInterface $parameterResolver,
        SearchEngine $searchEngineHelper,
        StoreManagerInterface $storeManager,
        NostoHelperAccount $nostoHelperAccount,
        CmpHelperData $cmpHelperData,
        StateAwareCategoryServiceInterface $categoryService,
        Logger $logger
    ) {
        $this->loggerTraitConstruct(
            $logger
        );
        $this->parameterResolver = $parameterResolver;
        $this->searchEngineHelper = $searchEngineHelper;
        $this->storeManager = $storeManager;
        $this->accountHelper = $nostoHelperAccount;
        $this->cmpHelperData = $cmpHelperData;
        $this->categoryService = $categoryService;
    }

    /**
     * @param array $requestData
     * @return void
     */
    public function handle(array &$requestData)
    {
        $this->debugWithSource('Using %s as search engine', [$this->searchEngineHelper->getCurrentEngine()]);
        $this->preFetchOps($requestData);
        Search::cleanUpCmpSort($requestData);
        try {
            $productIds = $this->getCmpProductIds(
                $this->getFilters($requestData),
                $this->parsePageNumber($requestData),
                $this->parseLimit($requestData)
            );
        } catch (Exception $e) {
            $this->logger->exception($e);
            return;
        }
        if (empty($productIds)) {
            $this->debugWithSource('Nosto did not return products for the request', [], $requestData);
            $this->setFallbackSort($requestData);
            return;
        }
        $this->applyCmpFilter(
            $requestData,
            $productIds
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
     * @param array $requestData
     * @return FacetInterface
     */
    abstract protected function getFilters(array $requestData);

    /**
     * Set fallback sort order
     *
     * @param array $requestData
     */
    private function setFallbackSort(array &$requestData)
    {
        try {
            $store = $this->storeManager->getStore();
            $sorting = $this->cmpHelperData->getFallbackSorting($store);
            $requestData['sort'][] = [
                'field' => $sorting,
                'direction' => 'ASC'
            ];
        } catch (Exception $e) {
            $this->debugWithSource("Could not set fallback sorting. %s", [$e->getMessage()]);
        }
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
            'field' => $this->getProductIdField(),
            'type' => 'termFilter',
            'value' => $productIds
        ];
        $requestData['size'] = $this->categoryService->getLastUsedLimit();
    }

    /**
     * @param array $requestData
     * @return int
     * @throws Exception
     */
    abstract public function parsePageNumber(array $requestData);

    /**
     * @param array $requestData
     * @return int
     * @throws Exception
     */
    abstract public function parseLimit(array $requestData);

    /**
     * @param FacetInterface $facet
     * @param $pageNum
     * @param $limit
     * @return array|null
     */
    private function getCmpProductIds(FacetInterface $facet, $pageNum, $limit)
    {
        try {
            $res = $this->categoryService->getPersonalisationResult(
                $facet,
                $pageNum,
                $limit
            );
            return $res ? CategoryMerchandising::parseProductIds($res) : null;
        } catch (Exception $e) {
            $this->logger->exception($e);
            return null;
        }
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
