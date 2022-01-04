<?php /** @noinspection PhpUnusedParameterInspection */
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

namespace Nosto\Cmp\Plugin\CatalogGraphQl\Products\DataProvider;

use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ProductSearch as MagentoProductSearch;
use Magento\Framework\Api\SearchResultsInterface;
use Nosto\Cmp\Model\Service\Recommendation\StateAwareCategoryService;
use Nosto\Cmp\Utils\CategoryMerchandising;

/**
 * Class used to re-sort products when served through Magento's GraphQl
 */
class ProductSearch
{
    /**
     * @var StateAwareCategoryService
     */
    private $categoryService;

    /**
     * SearchResultSorter constructor.
     * @param StateAwareCategoryService $categoryService
     */
    public function __construct(
        StateAwareCategoryService $categoryService
    ) {
        $this->categoryService = $categoryService;
    }

    /**
     * @param MagentoProductSearch $productSearch
     * @param SearchResultsInterface $result
     * @return SearchResultsInterface
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function afterGetList(MagentoProductSearch $productSearch, SearchResultsInterface $result)
    {
        $cmp = $this->getCmpSort();
        if (!empty($cmp)) {
            $items = $result->getItems();
            $newSorting = [];
            foreach ($cmp as $productId) {
                //the items are represented as a key value pair array, the key is the product id
                if (array_key_exists($productId, $items)) {
                    $newSorting[$productId] = $items[$productId];
                }
            }
            $result->setItems($newSorting);
        }
        return $result;
    }

    /**
     * Returns the product ids sorted by Nosto
     * or null in case CM is not applied or does not return any results
     * @return int[]|null
     */
    private function getCmpSort()
    {
        $categoryMerchandisingResult = $this->categoryService->getLastResult();
        if ($categoryMerchandisingResult !== null) {
            return CategoryMerchandising::parseProductIds(
                $categoryMerchandisingResult
            );
        }
        return null;
    }
}
