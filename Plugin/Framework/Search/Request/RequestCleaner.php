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
use Magento\Framework\Search\Request\Cleaner;
use Nosto\Cmp\Utils\Search;
use Nosto\Tagging\Logger\Logger;

class RequestCleaner
{
    const KEY_BIND_TO_QUERY = 'catalog_view_container';
    const KEY_BIND_TO_GRAPHQL = 'graphql_product_search';
    const KEY_CATEGORY_FILTER = 'category_filter';
    const KEY_QUERIES = 'queries';
    const KEY_FILTERS = 'filters';

    /** @var GraphQlHandler */
    private $graphqlHandler;

    /** @var WebHandler */
    private $webHandler;

    /** @var Logger  */
    private $logger;

    /**
     * RequestCleaner constructor.
     *
     * @param WebHandler $webHandler
     * @param GraphQlHandler $graphQlHandler
     * @param Logger $logger
     */
    public function __construct(
        WebHandler $webHandler,
        GraphQlHandler $graphQlHandler,
        Logger $logger
    ) {
        $this->webHandler = $webHandler;
        $this->graphqlHandler = $graphQlHandler;
        $this->logger = $logger;
    }

    /**
     * Cleans non persisted sorting parameters etc. for the request data
     *
     * @param Cleaner $cleaner
     * @param array $requestData
     * @return array
     * @noinspection PhpUnusedParameterInspection
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function afterClean(Cleaner $cleaner, array $requestData)
    {
        if (!Search::isNostoSorting($requestData) || !Search::hasCategoryFilter($requestData)) {
            $this->logger->debugWithSource(
                'Nosto sorting not used or not found from request data',
                $requestData,
                $this
            );
            //remove nosto_personalised in case it's a search page
            Search::cleanUpCmpSort($requestData);
            return $requestData;
        }
        try {

            if ($this->containsCatalogViewQueries($requestData)) {
                $this->webHandler->handle($requestData);
            } elseif ($this->containsGraphQlProductSearchQueries($requestData)) {
                $this->graphqlHandler->handle($requestData);
            } else {
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
        } catch (Exception $e) {
            $this->logger->debugWithSource(
                'Failed to apply CMP - see exception log(s) for details',
                $requestData,
                $this
            );
            $this->logger->exception($e);
        } finally {
            return $requestData;
        }
    }

    private function containsCatalogViewQueries(array $requestData)
    {
        if (isset($requestData[self::KEY_QUERIES][self::KEY_BIND_TO_QUERY])
            && isset($requestData[self::KEY_QUERIES][self::KEY_BIND_TO_QUERY]['queryReference'])) {
            return true;
        }
        return false;
    }

    private function containsGraphQlProductSearchQueries(array $requestData)
    {
        if (isset($requestData[self::KEY_QUERIES][self::KEY_BIND_TO_GRAPHQL])
            && isset($requestData[self::KEY_QUERIES][self::KEY_BIND_TO_GRAPHQL]['queryReference'])
            && isset($requestData[self::KEY_FILTERS][self::KEY_CATEGORY_FILTER])) {
            return true;
        }
        return false;
    }
}
