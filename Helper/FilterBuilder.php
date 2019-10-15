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

namespace Nosto\Cmp\Helper;

use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Nosto\Operation\Recommendation\IncludeFilters;
use Nosto\Operation\Recommendation\ExcludeFilters;
use Nosto\Tagging\Helper\Data as NostoHelperData;

class FilterBuilder
{
    /** @var IncludeFilters */
    private $includeFilters;

    /** @var ExcludeFilters */
    private $excludeFilters;

    /** @var NostoHelperData */
    private $nostoHelperData;

    /** @var string */
    private $brand;

    /**
     * FilterMapper constructor.
     * @param IncludeFilters $includeFilters
     * @param NostoHelperData $nostoHelperData
     */
    public function __construct(
        IncludeFilters $includeFilters,
        ExcludeFilters $excludeFilters,
        NostoHelperData $nostoHelperData
    ) {
        $this->includeFilters = $includeFilters;
        $this->excludeFilters = $excludeFilters;
        $this->nostoHelperData = $nostoHelperData;
    }

    /**
     * @param Store $store
     */
    public function init(Store $store): void
    {
        $this->brand = $this->nostoHelperData->getBrandAttribute($store);
    }

    /**
     * @param Item[] $filters
     * @throws LocalizedException
     */
    public function buildFromSelectedFilters($filters)
    {
        foreach ($filters as $filter) {
            $this->mapIncludeFilter($filter);
        }
    }

    /**
     * @param Item $item
     * @throws LocalizedException
     */
    public function mapIncludeFilter(Item $item): void
    {
        /** @var string $frontendInput */
        $frontendInput = $item->getFilter()->getData('attribute_model')
            ->getData('frontend_input');

        if ($frontendInput === null) {
            return;
        }

        $filterName = $item->getName();
        $value = '';
        switch ($frontendInput) {
            case 'price':
                $value = $item->getData('value');
                break;
            case 'select':
            case 'multiselect':
                $value = $item->getData('label');
                break;
            case 'date':
                break;
            case 'boolean':
                $value = $item->getData('value') === '1';
                break;
        }
        $this->mapValueToFilter($filterName, $value);
    }

    /**
     * @param string $name
     * @param string|array $value
     */
    private function mapValueToFilter(string $name, $value)
    {
        if ($this->brand === $name) {
            $this->includeFilters->setBrands($this->makeArrayFromValue($value));
            return;
        }

        switch (strtolower($name)) {
            case 'price':
                $this->includeFilters->setPrice(min($value), max($value));
                break;
            case 'new':
                $this->includeFilters->setFresh($value);
                break;
            default:
                $this->includeFilters->setCustomFields($name, $this->makeArrayFromValue($value));
                break;
        }
    }

    /**
     * @return IncludeFilters
     */
    public function getIncludeFilters(): IncludeFilters
    {
        return $this->includeFilters;
    }

    /**
     * @return ExcludeFilters
     */
    public function getExcludeFilters(): ExcludeFilters
    {
        return $this->excludeFilters;
    }

    /**
     * @param string|array $value
     * @return array
     */
    private function makeArrayFromValue($value)
    {
        if (is_string($value)) {
            $value = [$value];
        }
        return $value;
    }
}
