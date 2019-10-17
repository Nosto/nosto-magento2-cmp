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

namespace Nosto\Cmp\Model\Service\Recommendation;

use Exception;
use Nosto\Object\Signup\Account as NostoAccount;
use Nosto\Operation\AbstractGraphQLOperation;
use Nosto\Service\FeatureAccess;
use Nosto\Operation\Recommendation\CategoryMerchandising;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Cmp\Model\Filter\FilterBuilder;

class Category
{
    const NOSTO_PREVIEW_COOKIE = 'nostopreview';
    const MAX_PRODUCT_AMOUNT = 100;

    private $logger;
    private $cookieManager;

    /**
     * Category constructor.
     * @param CookieManagerInterface $cookieManager
     * @param NostoLogger $logger
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        NostoLogger $logger
    ) {
        $this->cookieManager = $cookieManager;
        $this->logger = $logger;
    }

    /**
     * @param NostoAccount $nostoAccount
     * @param FilterBuilder $filters
     * @param $nostoCustomerId
     * @param $category
     * @param int $skipPages
     * @param int $limit
     * @return CategoryMerchandisingResult|null
     */
    public function getPersonalisationResult(
        NostoAccount $nostoAccount,
        FilterBuilder $filters,
        $nostoCustomerId,
        $category,
        $skipPages,
        $limit = self::MAX_PRODUCT_AMOUNT
    ) {
        $limit = self::MAX_PRODUCT_AMOUNT < $limit ? self::MAX_PRODUCT_AMOUNT : $limit;
        $result = null;
        $featureAccess = new FeatureAccess($nostoAccount);
        if (!$featureAccess->canUseGraphql()) {
            return null;
        }
        $previewMode = (bool)$this->cookieManager->getCookie(self::NOSTO_PREVIEW_COOKIE);
        $categoryMerchandising = new CategoryMerchandising(
            $nostoAccount,
            $nostoCustomerId,
            $category,
            $skipPages,
            $filters->getIncludeFilters(),
            $filters->getExcludeFilters(),
            '',
            AbstractGraphQLOperation::IDENTIFIER_BY_CID,
            $previewMode,
            $limit
        );

        try {
            $result = $categoryMerchandising->execute();
        } catch (Exception $e) {
            $this->logger->exception($e);
        }
        return $result;
    }
}
