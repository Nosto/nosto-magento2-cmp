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

namespace Nosto\Cmp\Model\Service\Recommendation;

use Magento\Framework\Event\ManagerInterface;
use Nosto\Cmp\Utils\CategoryMerchandising as CategoryMerchandisingUtil;
use Nosto\Model\Signup\Account as NostoAccount;
use Nosto\NostoException;
use Nosto\Operation\AbstractGraphQLOperation;
use Nosto\Operation\Recommendation\CategoryMerchandising;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Request\Http\Exception\HttpResponseException;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Cmp\Model\Filter\FiltersInterface;
use Nosto\Service\FeatureAccess;

class Category
{
    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        ManagerInterface $eventManager
    ) {
        $this->eventManager = $eventManager;
    }

    /**
     * @param NostoAccount $nostoAccount
     * @param FiltersInterface $filters
     * @param $nostoCustomerId
     * @param $category
     * @param int $pageNumber
     * @param int $limit
     * @param bool $previewMode
     * @return CategoryMerchandisingResult
     * @throws NostoException
     * @throws AbstractHttpException
     * @throws HttpResponseException
     */
    public function getPersonalisationResult(
        NostoAccount $nostoAccount,
        FiltersInterface $filters,
        $nostoCustomerId,
        $category,
        $pageNumber,
        $limit,
        $previewMode = false
    ) {
        $featureAccess = new FeatureAccess($nostoAccount);
        if (!$featureAccess->canUseGraphql()) {
            throw new NostoException('Missing Nosto API_APPS token');
        }
        $categoryMerchandising = new CategoryMerchandising(
            $nostoAccount,
            $nostoCustomerId,
            $category,
            $pageNumber,
            $filters->getIncludeFilters(),
            $filters->getExcludeFilters(),
            '',
            AbstractGraphQLOperation::IDENTIFIER_BY_CID,
            $previewMode,
            $limit
        );
        $this->eventManager->dispatch(
            CategoryMerchandisingUtil::DISPATCH_EVENT_NAME_PRE_RESULTS,
            [
                CategoryMerchandisingUtil::DISPATCH_EVENT_KEY_REQUEST => $categoryMerchandising
            ]
        );
        /** @var CategoryMerchandisingResult $result */
        $result = $categoryMerchandising->execute();
        $this->eventManager->dispatch(
            CategoryMerchandisingUtil::DISPATCH_EVENT_NAME_POST_RESULTS,
            [
                CategoryMerchandisingUtil::DISPATCH_EVENT_KEY_REQUEST => $categoryMerchandising,
                CategoryMerchandisingUtil::DISPATCH_EVENT_KEY_RESULT => $result
            ]
        );
        return $result;
    }
}
