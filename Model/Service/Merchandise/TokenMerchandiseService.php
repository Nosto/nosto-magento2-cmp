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

namespace Nosto\Cmp\Model\Service\Merchandise;

use Nosto\Cmp\Model\Merchandise\MerchandiseRequestParams;
use Nosto\Cmp\Model\Service\MagentoSession\BatchModel;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Cmp\Model\Service\MagentoSession\SessionService;

class TokenMerchandiseService implements MerchandiseServiceInterface
{
    /** @var MerchandiseServiceInterface */
    private $merchandiseService;

    /** @var SessionService */
    private $sessionService;

    /**
     * @param MerchandiseServiceInterface $merchandiseService
     * @param SessionService $sessionService
     */
    public function __construct(MerchandiseServiceInterface $merchandiseService, SessionService $sessionService)
    {
        $this->merchandiseService = $merchandiseService;
        $this->sessionService = $sessionService;
    }

    /**
     * @param MerchandiseRequestParams $requestParams
     * @return CategoryMerchandisingResult
     */
    public function getMerchandiseResults(MerchandiseRequestParams $requestParams): CategoryMerchandisingResult
    {
        $result = $this->merchandiseService->getMerchandiseResults($requestParams);
        $this->handleBatchModel($requestParams, $result);
        return $result;
    }

    /**
     * @param MerchandiseRequestParams $requestParams
     * @param CategoryMerchandisingResult $result
     */
    private function handleBatchModel(MerchandiseRequestParams $requestParams, CategoryMerchandisingResult $result)
    {
        $batchModel = $this->getBatchModel();
        if ($results instanceof CategoryMerchandisingResult) {

            $batchModel->setBatchToken($results->getBatchToken());
            $batchModel->setTotalCount($results->getTotalPrimaryCount());
            $batchModel->setLastUsedLimit($requestParams->getLimit());
            $batchModel->setLastFetchedPage($requestParams->getPageNumber());
        }
        $this->session->setBatchModel($batchModel);
    }

    /**
     * @return BatchModel
     */
    private function getBatchModel()
    {
        $batchModel = $this->session->getBatchModel();
        if ($batchModel == null) {
            return new BatchModel();
        }
        return $batchModel;
    }
}
