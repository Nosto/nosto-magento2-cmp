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

namespace Nosto\Cmp\Plugin\Framework\Search\Request;

use Magento\Framework\Search\Request\Binder;
use Nosto\Cmp\Plugin\Catalog\Block\ParameterResolverInterface;
use Nosto\Cmp\Utils\Search;

class BinderMutator
{
    /**
     * @var ParameterResolverInterface
     */
    private $parameterResolver;

    /**
     * @param ParameterResolverInterface $parameterResolver
     */
    public function __construct(ParameterResolverInterface $parameterResolver)
    {
        $this->parameterResolver = $parameterResolver;
    }

    /**
     * Calls Nosto's API to get CMP result & sets product ids returned by Nosto to the bind data
     *
     * @param Binder $binder
     * @param array $data
     * @return array
     */
    public function afterBind(Binder $binder, array $data)
    {
        if (!Search::isNostoSortingByResolver($this->parameterResolver)) {
            return $data;
        }
        // TODO
        // - fetch Nosto data from Graphql
        // - add the product ids to the data & return
        // - double check if we should remove some of the existing bindings
        return $data;
    }
}