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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\LayeredNavigation\Block\Navigation\State;
use Magento\Store\Model\Store;
use Nosto\Cmp\Exception\AttributeValueException;
use Nosto\Cmp\Exception\FacetValueException;
use Nosto\Cmp\Exception\NotSupportedFrontedInputException;
use Nosto\Cmp\Helper\Data as CmpHelperData;
use Nosto\Cmp\Helper\SearchEngine;
use Nosto\Cmp\Model\Facet\FacetInterface;
use Nosto\Cmp\Model\Service\Facet\BuildWebFacetService;
use Nosto\Cmp\Model\Service\Recommendation\StateAwareCategoryServiceInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger;

class WebHandler extends AbstractHandler
{

    /** @var State */
    private $state;

    /** @var BuildWebFacetService  */
    private $buildWebFacetService;

    /** @var int  */
    private $pageSize;

    /**
     * WebHandler constructor.
     * @param SearchEngine $searchEngineHelper
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param CmpHelperData $cmpHelperData
     * @param StateAwareCategoryServiceInterface $categoryService
     * @param BuildWebFacetService $buildWebFacetService
     * @param State $state
     * @param Logger $logger
     * @param int $pageSize
     */
    public function __construct(
        SearchEngine $searchEngineHelper,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        CmpHelperData $cmpHelperData,
        StateAwareCategoryServiceInterface $categoryService,
        BuildWebFacetService $buildWebFacetService,
        State $state,
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
        $this->buildWebFacetService = $buildWebFacetService;
        $this->state = $state;
        $this->pageSize = $pageSize;
    }

    /**
     * @return string
     */
    public function getBindKey()
    {
        return self::KEY_BIND_TO_QUERY;
    }

    /**
     * @param array $requestData
     * @return null
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    protected function preFetchOps(array $requestData)
    {
        //No necessary operations here
        return null;
    }

    /**
     * @param Store $store
     * @param array $requestData
     * @return int
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function parseLimit(Store $store, array $requestData)
    {
        if ($this->pageSize != -1) {
            $this->trace('Using DI value (%s) for the page size', [$this->pageSize]);

            return $this->pageSize;
        }
        return (int) $requestData[self::KEY_RESULT_SIZE];
    }

    /**
     * @param Store $store
     * @param array $requestData
     * @return int
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function parsePageNumber(Store $store, array $requestData)
    {
        $from = $requestData[self::KEY_RESULTS_FROM];
        if ($from < 1) {
            return 0;
        }
        return (int) ceil($from / $this->parseLimit($store, $requestData));
    }

    /**
     * @param Store $store
     * @param array $requestData
     * @return FacetInterface
     * @throws AttributeValueException
     * @throws FacetValueException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws NotSupportedFrontedInputException
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function getFilters(Store $store, array $requestData)
    {
        return $this->buildWebFacetService->getFacets($store);
    }
}
