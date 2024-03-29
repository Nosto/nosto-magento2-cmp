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

namespace Nosto\Cmp\Plugin\Api\Search;

use Magento\Framework\Api\Search\Document;
use Magento\Framework\Api\Search\SearchResult;
use Nosto\Cmp\Model\Service\Merchandise\LastResult;

class SearchResultSorter
{
    /** @var LastResult  */
    private LastResult $lastResult;

    /**
     * @param LastResult $lastResult
     */
    public function __construct(
        LastResult $lastResult
    ) {
        $this->lastResult = $lastResult;
    }

    /**
     * @param SearchResult $subject
     * @param $result
     * @return array
     */
    public function beforeSetItems(SearchResult $subject, $result)
    {
        $cmpSort = $this->getCmpSort();
        if (empty($cmpSort)) {
            return [$result];
        }
        $sorted = [];
        foreach ($cmpSort as $productId) {
            $document = $this->findDocumentByProductId($result, $productId);
            if ($document) {
                $sorted[] = $document;
            }
        }
        $subject->setTotalCount($this->getTotalPrimaryCount());
        return [$sorted];
    }

    /**
     * @param Document[] $result
     * @param $productId
     * @return Document|null
     */
    private function findDocumentByProductId(array $result, $productId)
    {
        foreach ($result as $document) {
            if ($document->getId() == $productId) {
                return $document;
            }
        }
        return null;
    }

    /**
     * Returns the product ids sorted by Nosto
     * @return int[]|null
     */
    private function getCmpSort()
    {
        $categoryMerchandisingResult = $this->lastResult->getLastResult();
        if ($categoryMerchandisingResult !== null) {
            return $categoryMerchandisingResult->parseProductIds();
        }
        return null;
    }

    /**
     * Returns the product ids sorted by Nosto
     * @return int|null
     */
    private function getTotalPrimaryCount()
    {
        $categoryMerchandisingResult = $this->lastResult->getLastResult();
        if ($categoryMerchandisingResult !== null) {
            return $categoryMerchandisingResult->getTotalPrimaryCount();
        }
        return null;
    }
}
