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

namespace Nosto\Cmp\Block;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
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

    public function getCategoryMap() {

        $array = [];
        $store = $this->storeManager->getStore();
        if ($store instanceof Store) {
            $array = $this->getMagentoCategories($store);
        }

        return json_encode((object) $array, JSON_UNESCAPED_SLASHES);
    }

    private function getMagentoCategories(Store $store) {

        $baseUrl = '';
        $categoriesArray = [];

        try {
            $baseUrl = $store->getBaseUrl();
        } catch (\Exception $e) {
            $this->logger->exception(sprintf("Could not fetch base url for store %s",
                $store->getName())
            );
        }

        $categories = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('include_in_menu', array('eq' => 1))
            ->setStore($store);



        /** @var \Magento\Catalog\Model\Category $item */
        foreach ($categories->getItems() as $category) {
            $nostoCategoryString = strtolower(
                $this->categoryBuilder->getCategory($category, $store)
            );
            $categoryUrl = $baseUrl . '' . $category->getUrlPath();
            if ($nostoCategoryString) {
                $categoriesArray[$nostoCategoryString] = $categoryUrl;
            }
        }
        return $categoriesArray;
    }
}
