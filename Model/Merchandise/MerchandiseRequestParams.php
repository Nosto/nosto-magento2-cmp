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

namespace Nosto\Cmp\Model\Merchandise;

use Nosto\Cmp\Model\Facet\FacetInterface;
use Nosto\Types\Signup\AccountInterface;

class MerchandiseRequestParams
{
    /** @var AccountInterface */
    private $nostoAccount;

    /** @var FacetInterface */
    private $facets;

    /** @var string */
    private $customerId;

    /** @var string */
    private $category;

    /** @var int */
    private $pageNumber;

    /** @var int */
    private $limit;

    /** @var bool */
    private $previewMode;

    /** @var string */
    private $batchToken;

    /**
     * @param AccountInterface $nostoAccount
     * @param FacetInterface $facets
     * @param string $customerId
     * @param string $category
     * @param int $pageNumber
     * @param int $limit
     * @param bool $previewMode
     * @param string $batchToken
     */
    public function __construct(
        AccountInterface $nostoAccount,
        FacetInterface $facets,
        string $customerId,
        string $category,
        int $pageNumber,
        int $limit,
        bool $previewMode,
        string $batchToken
    ) {
        $this->nostoAccount = $nostoAccount;
        $this->facets = $facets;
        $this->customerId = $customerId;
        $this->category = $category;
        $this->pageNumber = $pageNumber;
        $this->limit = $limit;
        $this->previewMode = $previewMode;
        $this->batchToken = $batchToken;
    }


    /**
     * @return AccountInterface
     */
    public function getNostoAccount(): AccountInterface
    {
        return $this->nostoAccount;
    }

    /**
     * @param AccountInterface $nostoAccount
     */
    public function setNostoAccount(AccountInterface $nostoAccount): void
    {
        $this->nostoAccount = $nostoAccount;
    }

    /**
     * @return FacetInterface
     */
    public function getFacets(): FacetInterface
    {
        return $this->facets;
    }

    /**
     * @param FacetInterface $facets
     */
    public function setFacets(FacetInterface $facets): void
    {
        $this->facets = $facets;
    }

    /**
     * @return string
     */
    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    /**
     * @param string $customerId
     */
    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * @param string $category
     */
    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    /**
     * @return int
     */
    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    /**
     * @param int $pageNumber
     */
    public function setPageNumber(int $pageNumber): void
    {
        $this->pageNumber = $pageNumber;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @return bool
     */
    public function isPreviewMode(): bool
    {
        return $this->previewMode;
    }

    /**
     * @param bool $previewMode
     */
    public function setPreviewMode(bool $previewMode): void
    {
        $this->previewMode = $previewMode;
    }

    /**
     * @return string
     */
    public function getBatchToken(): string
    {
        return $this->batchToken;
    }

    /**
     * @param string $batchToken
     */
    public function setBatchToken(string $batchToken): void
    {
        $this->batchToken = $batchToken;
    }

}
