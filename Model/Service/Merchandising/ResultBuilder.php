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

use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Cmp\Model\Filter\FilterBuilder as NostoFilterBuilder;
use Nosto\Cmp\Model\Service\Recommendation\Category as CategoryRecommendation;
use Nosto\Cmp\Utils\Debug\ServerTiming;
use Nosto\Cmp\Model\Service\Merchandising\Result;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class ResultBuilder
{
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

    private $logger;

    /**
     * Result constructor.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoFilterBuilder $nostoFilterBuilder
     * @param CategoryRecommendation $categoryRecommendation
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoFilterBuilder $nostoFilterBuilder,
        CategoryRecommendation $categoryRecommendation,
        NostoLogger $logger
    ) {
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoFilterBuilder = $nostoFilterBuilder;
        $this->categoryRecommendation = $categoryRecommendation;
        $this->logger = $logger;
    }

    /**
     * @param string $nostoCustomerId
     */
    public function setNostoCustomerId(string $nostoCustomerId): void
    {
        $this->nostoCustomerId = $nostoCustomerId;
    }

    /**
     * @param string $category
     */
    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    /**
     * @param int $currentPage
     */
    public function setCurrentPage(int $currentPage): void
    {
        $this->currentPage = $currentPage;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @return \Nosto\Cmp\Model\Service\Merchandising\Result
     */
    public function build() {
        return new Result(
            $this->nostoHelperAccount,
            $this->nostoFilterBuilder,
            $this->categoryRecommendation,
            $this->logger,
            $this->nostoCustomerId,
            $this->category,
            $this->currentPage,
            $this->limit
        );
    }
}