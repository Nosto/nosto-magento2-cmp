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

namespace Nosto\Cmp\Plugin\Catalog\Model\Config\Source;

use Nosto\Cmp\Helper\Data as NostoCmpHelperData;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Model\Config\Source\ListSort as MagentoListSort;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Cmp\Helper\CategorySorting as NostoHelperSorting;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;

class ListSort
{

    /** @var NostoCmpHelperData */
    private $nostoCmpHelperData;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var StoreManagerInterface */
    private $storeManager;

    /**
     * ListSort constructor.
     * @param NostoCmpHelperData $nostoCmpHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param Context $context
     */
    public function __construct(
        NostoCmpHelperData $nostoCmpHelperData,
        NostoHelperAccount $nostoHelperAccount,
        Context $context
    ) {
        $this->nostoCmpHelperData = $nostoCmpHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->storeManager = $context->getStoreManager();
    }

    /**
     * @param MagentoListSort $listSort
     * @param array $options
     * @return array
     * @throws NoSuchEntityException
     */
    public function afterToOptionArray( // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        MagentoListSort $listSort,
        array $options
    ) {
        /* @var Store $store */
        $store = $this->storeManager->getStore();
        //@phan-suppress-next-line PhanTypeMismatchArgument
        if ($this->nostoHelperAccount->nostoInstalledAndEnabled($store) &&
            $this->nostoCmpHelperData->isCategorySortingEnabled($store)
        ) {
            $customOption = [
                //@phan-suppress-next-line PhanTypeMismatchArgument
                ['label' => __('Relevance'), 'value' => NostoHelperSorting::NOSTO_PERSONALIZED_KEY]
            ];

            // merge default sorting options with custom options
            $options = array_merge($options, $customOption);
        }

        return $options;
    }
}
