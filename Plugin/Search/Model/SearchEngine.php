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

namespace Nosto\Cmp\Plugin\Search\Model;

use Magento\Framework\Search\Request;
use Magento\Search\Model\SearchEngine as MagentoSearchEngine;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Request\Builder;
use Nosto\Cmp\Helper\CategorySorting;

class SearchEngine
{
    /** @var Builder  */
    public $requestBuilder;

    /**
     * SearchEngine constructor.
     * @param Builder $requestBuilder
     */
    public function __construct(Builder $requestBuilder)
    {
        $this->requestBuilder = $requestBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSearch(
        MagentoSearchEngine $searchEngine,
        RequestInterface $request
    ) {
        $sortOrder = $request->getSort();
        if (isset($sortOrder[0]['field']) &&
            $sortOrder[0]['field'] === CategorySorting::NOSTO_PERSONALIZED_KEY
        ) {
            $this->removeSorting($request);
        }

    }

    /**
     * @param RequestInterface $request
     * @throws \ReflectionException
     */
    private function removeSorting(RequestInterface $request)
    {
        $reflectionClass = new \ReflectionClass(Request::class);
        $sortProperty = $reflectionClass->getProperty('sort');
        $sortProperty->setAccessible(true);
        $sortProperty->setValue($request, []);
    }
}
