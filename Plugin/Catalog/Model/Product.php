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

namespace Nosto\Cmp\Plugin\Catalog\Model;

use Magento\Catalog\Model\Product as MagentoProduct;

class Product
{
    const NOSTO_TRACKING_PARAMETER_NAME = 'nosto_cmp';

    /**
     * @param MagentoProduct $product
     * @param string $url
     * @return string
     * @noinspection PhpUnused
     */
    public function afterGetProductUrl(MagentoProduct $product, $url)
    {
        if ($product->getData(self::NOSTO_TRACKING_PARAMETER_NAME) !== null) {
            $url = $product->getUrlModel()->getUrl(
                $product,
                [
                    '_query' => $this->parseExistingQueryParams($url),
                    '_fragment' => self::NOSTO_TRACKING_PARAMETER_NAME
                ]
            );
        }
        return $url;
    }

    /**
     * Get query params from a url
     *
     * @param string $url
     * @return array
     */
    private function parseExistingQueryParams($url)
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged, Ecg.Security.ForbiddenFunction.Found
        $parsed = parse_url($url, PHP_URL_QUERY);
        // This is in case of PHP 8.0+, which now returns null if the requested component is not in the URL
        if (!empty($parsed)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged, Ecg.Security.ForbiddenFunction.Found
            parse_str($parsed, $result);
        } else {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged, Ecg.Security.ForbiddenFunction.Found
            parse_str($url, $result);
        }
        return $result;
    }
}
