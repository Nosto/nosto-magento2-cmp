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

namespace Nosto\Cmp\Plugin\Catalog\Helper;

use Magento\Catalog\Helper\Category as CategoryHelper;
use Nosto\Cmp\Plugin\Catalog\Block\DefaultParameterResolver;
use Magento\Framework\UrlInterface;
use Nosto\Cmp\Helper\CategorySorting;
use Purl\Url;

class Category
{

    const NOSTO_CMP_FRAGMENT = 'nosto_cmp';

    /** @var UrlInterface */
    private $url;

    public function __construct(UrlInterface $url)
    {
        $this->url = $url;
    }
    /**
     * @param CategoryHelper $categoryHelper
     * @param $categoryUrl
     * @return string
     * @suppress PhanUndeclaredMethod
     */
    public function afterGetCategoryUrl( // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        CategoryHelper $categoryHelper,
        $categoryUrl
    ) {
        $currentUrl = $this->url->getCurrentUrl();
        $nostoSortParam = sprintf(
            '%s=%s',
            DefaultParameterResolver::DEFAULT_SORTING_ORDER_PARAM,
            CategorySorting::NOSTO_PERSONALIZED_KEY
        );
        // phpcs:ignore Ecg.Strings.StringPosition.ImproperValueTesting,Magento2.PHP.ReturnValueCheck.ImproperValueTesting
        if (strpos($currentUrl, $nostoSortParam)) {
            return (new Url($categoryUrl))->set('fragment', self::NOSTO_CMP_FRAGMENT);
        }
        return new Url($categoryUrl);
    }
}
