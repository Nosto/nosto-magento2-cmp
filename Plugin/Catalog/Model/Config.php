<?php /** @noinspection PhpUnusedParameterInspection */
/** @noinspection PhpUnused */
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

namespace Nosto\Cmp\Plugin\Catalog\Model;

use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Model\Config as MagentoConfig;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\AttributeFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Cmp\Helper\CategorySorting as NostoHelperSorting;
use Nosto\Cmp\Helper\Data as NostoCmpHelperData;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;

class Config
{
    /** @var NostoCmpHelperData */
    private $nostoCmpHelperData;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var AttributeFactory */
    private $attributeFactory;

    /**
     * Config constructor.
     * @param NostoCmpHelperData $nostoCmpHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param AttributeFactory $attributeFactory
     * @param Context $context
     */
    public function __construct(
        NostoCmpHelperData $nostoCmpHelperData,
        NostoHelperAccount $nostoHelperAccount,
        AttributeFactory $attributeFactory,
        Context $context
    ) {
        $this->nostoCmpHelperData = $nostoCmpHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->attributeFactory = $attributeFactory;
        $this->storeManager = $context->getStoreManager();
    }

    /**
     * Add custom Sorting attribute
     *
     * @param MagentoConfig $catalogConfig
     * @param $options
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws NoSuchEntityException
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterGetAttributeUsedForSortByArray(// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        MagentoConfig $catalogConfig,
        $options
    ) {
        /* @var Store $store */
        $store = $this->storeManager->getStore();
        //@phan-suppress-next-line PhanTypeMismatchArgument
        if ($this->nostoHelperAccount->nostoInstalledAndEnabled($store) &&
            $this->nostoCmpHelperData->isCategorySortingEnabled($store)
        ) {
            // new option
            $customOptions = NostoHelperSorting::getNostoSortingOptions();

            // merge default sorting options with custom options
            $options = array_merge($customOptions, $options);
        }

        return $options;
    }

    /**
     * @param MagentoConfig $catalogConfig
     * @param $options
     * @return array
     * @throws NoSuchEntityException
     */
    public function afterGetAttributesUsedForSortBy(// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        MagentoConfig $catalogConfig,
        $options
    ) {
        /* @var Store $store */
        $store = $this->storeManager->getStore();
        //@phan-suppress-next-line PhanTypeMismatchArgument
        if ($this->nostoHelperAccount->nostoInstalledAndEnabled($store) &&
            $this->nostoCmpHelperData->isCategorySortingEnabled($store)
        ) {

            $eavAttribute = $this->attributeFactory->createAttribute(Attribute::class);
            $eavAttribute->setAttributeCode(NostoHelperSorting::NOSTO_PERSONALIZED_KEY);
            //@phan-suppress-next-line PhanTypeMismatchArgument
            $eavAttribute->setDefaultFrontendLabel(__('Relevance'));

            $options[] = $eavAttribute;
        }

        return $options;
    }
}
