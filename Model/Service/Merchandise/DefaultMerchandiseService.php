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

namespace Nosto\Cmp\Model\Service\Merchandise;

use Magento\Framework\Event\ManagerInterface;
use Nosto\Cmp\Model\Merchandise\MerchandiseRequestParams;
use Nosto\Cmp\Model\Service\Merchandise\LastResult;
use Nosto\Cmp\Observer\App\Action\PostRequestAction;
use Nosto\Cmp\Observer\App\Action\PreRequestAction;
use Nosto\Cmp\Utils\Debug\ServerTiming;
use Nosto\NostoException;
use Nosto\Operation\AbstractGraphQLOperation;
use Nosto\Operation\Recommendation\BatchedCategoryMerchandising;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Request\Http\Exception\HttpResponseException;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;

class DefaultMerchandiseService implements MerchandiseServiceInterface
{

    const TIME_PROF_GRAPHQL_QUERY = 'cmp_graphql_query';

    /** @var ManagerInterface  */
    private $eventManager;

    /** @var LastResult */
    private $lastResult;

    /**
     * @param ManagerInterface $eventManager
     * @param LastResult $lastResult
     */
    public function __construct(
        ManagerInterface $eventManager,
        LastResult $lastResult
    ) {
        $this->eventManager = $eventManager;
        $this->lastResult = $lastResult;
    }

    /**
     * @param MerchandiseRequestParams $requestParams
     * @return CategoryMerchandisingResult
     */
    public function getMerchandiseResults(MerchandiseRequestParams $requestParams): CategoryMerchandisingResult
    {
        $result = ServerTiming::getInstance()->instrument(
            function () use ($requestParams) {
                return $this->getResults($requestParams);
            },
            self::TIME_PROF_GRAPHQL_QUERY
        );

        //This will be used by other interceptors
        $this->lastResult->setLastResult($result);

        return $result;
    }

    /**
     * @param MerchandiseRequestParams $requestParams
     * @return CategoryMerchandisingResult
     * @throws AbstractHttpException
     * @throws HttpResponseException
     * @throws NostoException
     */
    private function getResults(MerchandiseRequestParams $requestParams)
    {
        $categoryMerchandising = new BatchedCategoryMerchandising(
            $requestParams->getNostoAccount(),
            $requestParams->getCustomerId(),
            $requestParams->getCategory(),
            $requestParams->getPageNumber(),
            $requestParams->getFacets()->getIncludeFilters(),
            $requestParams->getFacets()->getExcludeFilters(),
            '',
            AbstractGraphQLOperation::IDENTIFIER_BY_CID,
            $requestParams->isPreviewMode(),
            $requestParams->getLimit(),
            $requestParams->getBatchToken()
        );

        $result = $categoryMerchandising->execute();

        $this->eventManager->dispatch(
            PostRequestAction::DISPATCH_EVENT_NAME_POST_RESULTS,
            [
                PostRequestAction::DISPATCH_EVENT_KEY_RESULT => $result,
                PostRequestAction::DISPATCH_EVENT_KEY_LIMIT => $requestParams->getLimit(),
                PostRequestAction::DISPATCH_EVENT_KEY_PAGE => $requestParams->getPageNumber(),
            ]
        );
        return $result;
    }

}
