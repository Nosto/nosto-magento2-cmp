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

namespace Nosto\Cmp\Model\Search;

use Magento\Framework\Search\Request as MagentoRequest;
use Magento\Framework\Search\Request\QueryInterface;
use Magento\Framework\Search\RequestInterface;
use Nosto\Cmp\Utils\Request as RequestUtils;
use Magento\Framework\Search\Request\Query\BoolExpression;

class Request implements RequestInterface
{
    /**
     * @var MagentoRequest
     */
    protected $request;

    /**
     * Request constructor.
     * @param MagentoRequest $request
     */
    public function __construct(MagentoRequest $request)
    {
        $this->request = $request;
    }

    /**
     * @return BoolExpression|QueryInterface
     */
    public function getPostFilter()
    {
        /** @var QueryInterface $query */
        $query = $this->request->getQuery();
        if ($query instanceof BoolExpression && RequestUtils::containsBoolNostoSearchQuery($query)) {
            $must['nosto_cmp_id_search'] = $query->getMust()[RequestUtils::NOSTO_CMP_REQUEST_QUERY];
            return new BoolExpression(
                $query->getName(),
                $query->getBoost(),
                $must,
                $query->getShould(),
                $query->getMustNot()
            );
        }
        return $query;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->request->getName();
    }

    /**
     * @return string
     */
    public function getIndex()
    {
        return $this->request->getIndex();
    }

    /**
     * @return MagentoRequest\Dimension[]
     */
    public function getDimensions()
    {
        return $this->request->getDimensions();
    }

    /**
     * @return MagentoRequest\BucketInterface[]
     */
    public function getAggregation()
    {
        return $this->request->getAggregation();
    }

    /**
     * @return BoolExpression|QueryInterface
     */
    public function getQuery()
    {
        /** @var QueryInterface $query */
        $query = $this->request->getQuery();
        if ($query instanceof BoolExpression && RequestUtils::containsBoolNostoSearchQuery($query)) {
            $must = $query->getMust();
            unset($must[RequestUtils::NOSTO_CMP_REQUEST_QUERY]);
            return new BoolExpression(
                $query->getName(),
                $query->getBoost(),
                $must,
                $query->getShould(),
                $query->getMustNot()
            );
        }
        return $query;
    }

    /**
     * @return int|null
     */
    public function getFrom()
    {
        return $this->request->getFrom();
    }

    /**
     * @return int|null
     */
    public function getSize()
    {
        return $this->request->getSize();
    }

    /**
     * @return array
     */
    public function getSort()
    {
        return $this->request->getSort();
    }
}
