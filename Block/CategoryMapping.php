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

namespace Nosto\Cmp\Block;

use Exception;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Service\Product\Category\DefaultCategoryService as CategoryBuilder;

class CategoryMapping extends Template
{
    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var CollectionFactory */
    private $collectionFactory;

    /** @var CategoryBuilder */
    private $categoryBuilder;

    /** @var NostoLogger */
    private $logger;

    /**
     * CategoryMapping constructor.
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $collectionFactory
     * @param CategoryBuilder $categoryBuilder
     * @param Context $context
     * @param NostoLogger $logger
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CollectionFactory $collectionFactory,
        CategoryBuilder $categoryBuilder,
        Context $context,
        NostoLogger $logger
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->collectionFactory = $collectionFactory;
        $this->categoryBuilder = $categoryBuilder;
        $this->logger = $logger;
    }

    /**
     * @return false|string
     */
    public function getCategoryMap()
    {

        $array = [];
        try {
            $store = $this->storeManager->getStore();
            if ($store instanceof Store) {
                $array = $this->getMagentoCategories($store);
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->exception($e);
        }

        return json_encode((object)$array, JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param Store $store
     * @return array
     * @suppress PhanTypeMismatchArgument
     */
    private function getMagentoCategories(Store $store)
    {

        $categoriesArray = [];

        try {
            $categories = $this->collectionFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('include_in_menu', ['eq' => 1])
                ->addIsActiveFilter()
                ->setStore($store);

            /** @var Category $category $item */
            foreach ($categories->getItems() as $category) {
                $hashedCategoryString = $this->hashCategoryString(strtolower(
                    $this->categoryBuilder->getCategory($category, $store)
                ));
                if ($hashedCategoryString) {
                    $categoriesArray[$hashedCategoryString] = $category->getUrl();
                }
            }
        } catch (Exception $e) {
            $this->logger->exception($e);
        }

        return $categoriesArray;
    }

    /**
     * @param String $categoryString
     * @return string
     */
    private function hashCategoryString($categoryString)
    {
        $signedInteger = crc32($categoryString);
        $unsignedInteger = (int)sprintf("%u", $signedInteger);
        return dechex($unsignedInteger);
    }
}
