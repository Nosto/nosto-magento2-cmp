<?php
/**
 * Copyright (c) 2019, Nosto Solutions Ltd
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
 * @copyright 2019 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Cmp\Model\Service\Merchandising;

use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Cmp\Model\Filter\FilterBuilder as NostoFilterBuilder;
use Nosto\Cmp\Model\Service\Recommendation\Category as CategoryRecommendation;
use Nosto\Cmp\Utils\Debug\ServerTiming;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Exception;

class Result
{
    const TIME_PROF_GRAPHQL_QUERY = 'cmp_graphql_query';

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoFilterBuilder  */
    private $nostoFilterBuilder;

    /** @var CategoryRecommendation */
    private $categoryRecommendation;

    /** @var string */
    private $nostoCustomerId;

    /** @var string */
    private $category;

    /** @var int */
    private $currentPage;

    /** @var int */
    private $limit;

    /** @var NostoLogger  */
    private $logger;

    /**
     * Results constructor.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoFilterBuilder $nostoFilterBuilder
     * @param CategoryRecommendation $categoryRecommendation
     * @param string $nostoCustomerId
     * @param string $category
     * @param int $currentPage
     * @param int $limit
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoFilterBuilder $nostoFilterBuilder,
        CategoryRecommendation $categoryRecommendation,
        NostoLogger $logger,
        $nostoCustomerId,
        $category,
        $currentPage,
        $limit
    ) {
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoFilterBuilder = $nostoFilterBuilder;
        $this->categoryRecommendation = $categoryRecommendation;
        $this->logger = $logger;
        $this->nostoCustomerId = $nostoCustomerId;
        $this->category = $category;
        $this->currentPage = $currentPage;
        $this->limit = $limit;
    }

    /**
     * @param Store $store
     * @return array
     */
    public function getSortingOrderResults(Store $store) {
        $cmpResults = $this->getCmpResult($store);
        return $this->parseProductIds($cmpResults);
    }

    /**
     * @param Store $store
     * @return CategoryMerchandisingResult
     * @throws NostoException
     * @throws MissingAppsTokenException
     * @throws LocalizedException
     */
    private function getCmpResult(Store $store)
    {
        $nostoAccount = $this->nostoHelperAccount->findAccount($store);
        if ($nostoAccount === null) {
            throw new NostoException('Account cannot be null');
        }

        // Build filters
        $this->nostoFilterBuilder->init($store);
//        $this->nostoFilterBuilder->buildFromSelectedFilters(
//            $this->state->getActiveFilters()
//        );

        return ServerTiming::getInstance()->instrument(
            function () use ($nostoAccount, $store) {
                return $this->categoryRecommendation->getPersonalisationResult(
                    $nostoAccount,
                    $this->nostoFilterBuilder,
                    $this->nostoCustomerId,
                    $this->category,
                    $this->currentPage - 1,
                    $this->limit
                );
            },
            self::TIME_PROF_GRAPHQL_QUERY
        );
    }

    /**
     * @param CategoryMerchandisingResult $result
     * @return array
     */
    private function parseProductIds(CategoryMerchandisingResult $result)
    {
        $productIds = [];
        try {
            foreach ($result->getResultSet() as $item) {
                if ($item->getProductId() && is_numeric($item->getProductId())) {
                    $productIds[] = $item->getProductId();
                }
            }
        } catch (Exception $e) {
            $this->logger->exception($e);
        }

        return $productIds;
    }
}