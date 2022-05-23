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

namespace Nosto\Cmp\Model\Service\Category;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Nosto\Cmp\Exception\JsonEncodeFailureException;
use Nosto\Cmp\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\Service\Product\Category\DefaultCategoryService as CategoryBuilder;

class CategoryMappingService implements CategoryMappingServiceInterface
{

    /** @var CollectionFactory */
    private CollectionFactory $collectionFactory;

    /** @var CategoryBuilder */
    private CategoryBuilder $categoryBuilder;

    /** @var NostoHelperData */
    private NostoHelperData $nostoHelperData;

    /**
     * @param CollectionFactory $collectionFactory
     * @param CategoryBuilder $categoryBuilder
     * @param NostoHelperData $nostoHelperData
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        CategoryBuilder $categoryBuilder,
        NostoHelperData $nostoHelperData
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->categoryBuilder = $categoryBuilder;
        $this->nostoHelperData = $nostoHelperData;
    }

    /**
     * @param Store $store
     * @return string
     * @throws LocalizedException
     * @throws JsonEncodeFailureException
     */
    public function getCategoryMapping(Store $store): string
    {
        $array = $this->getMagentoCategories($store);
        $mapping = json_encode((object)$array, JSON_UNESCAPED_SLASHES);
        if ($mapping) {
            throw new JsonEncodeFailureException($store, $array);
        }
        return $mapping;
    }

    /**
     * @param Store $store
     * @return array
     * @throws LocalizedException
     * @suppress PhanTypeMismatchArgument
     */
    private function getMagentoCategories(Store $store)
    {

        $categoriesArray = [];

        $categories = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addIsActiveFilter()
            ->setStore($store);

        if (!$this->nostoHelperData->isAllCategoriesMapEnabled($store)) {
            $categories->addAttributeToFilter('include_in_menu', ['eq' => 1]);
        }

        /** @var Category $category $item */
        foreach ($categories->getItems() as $category) {
            $categoryName = $this->categoryBuilder->getCategory($category, $store);
            if ($categoryName) {
                $hashedCategoryString = self::hashCategoryString(strtolower($categoryName));
                $categoriesArray[$hashedCategoryString] = $category->getUrl();
            }
        }

        return $categoriesArray;
    }

    /**
     * @param String $categoryString
     * @return string
     */
    private static function hashCategoryString(string $categoryString)
    {
        $signedInteger = crc32($categoryString);
        $unsignedInteger = (int)sprintf("%u", $signedInteger);
        return dechex($unsignedInteger);
    }
}
