<?php /** @noinspection PhpDeprecationInspection */

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

namespace Nosto\Cmp\Plugin\Catalog\Block;

use Magento\Catalog\Model\Category;
use Magento\Framework\App\Request\Http;
use /** @noinspection PhpDeprecationInspection */Magento\Framework\Registry;

class DefaultParameterResolver implements ParameterResolverInterface
{
    const DEFAULT_SORTING_ORDER_PARAM = 'product_list_order';
    const DEFAULT_CURRENT_PAGE_PARAM = 'p';

    /** @var Http */
    private $httpRequest;

    /** @var Registry */
    private $registry;
    /** @noinspection PhpDeprecationInspection */

    /**
     * DefaultParameterResolver constructor.
     * @param Http $httpRequest
     * @param Registry $registry
     * @noinspection PhpDeprecationInspection
     * @noinspection PhpUnused
     */
    public function __construct(Http $httpRequest, Registry $registry)
    {
        $this->httpRequest = $httpRequest;
        $this->registry = $registry;
    }

    /**
     * @inheritdoc
     * @noinspection PhpUnused
     */
    public function getSortingOrder()
    {
        return $this->httpRequest->getParam(
            self::DEFAULT_SORTING_ORDER_PARAM,
            $this->getDefaultCategorySorting()
        );
    }

    /**
     * @inheritdoc
     * @noinspection PhpUnused
     */
    public function getCurrentPage()
    {
        return (int)$this->httpRequest->getParam(self::DEFAULT_CURRENT_PAGE_PARAM, '1');
    }

    /**
     * @return string|null
     */
    private function getDefaultCategorySorting()
    {
        /**
         * @var Category $category
         * @noinspection PhpDeprecationInspection
         */
        $category = $this->registry->registry('current_category'); //@phan-suppress-current-line PhanDeprecatedFunction
        if ($category instanceof Category) {
            return $category->getDefaultSortBy();
        }
        return null;
    }
}
