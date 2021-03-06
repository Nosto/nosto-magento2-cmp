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

namespace Nosto\Cmp\Model\Filter;

use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Operation\Recommendation\ExcludeFilters;
use Nosto\Operation\Recommendation\IncludeFilters;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Cmp\Logger\LoggerInterface;

class WebFilters implements FiltersInterface
{
    /** @var IncludeFilters */
    private $includeFilters;

    /** @var ExcludeFilters */
    private $excludeFilters;

    /** @var NostoHelperData */
    private $nostoHelperData;

    /** @var string */
    private $brand;

    /** @var LoggerInterface */
    private $logger;

    /**
     * FilterBuilder constructor.
     * @param IncludeFilters $includeFilters
     * @param ExcludeFilters $excludeFilters
     * @param NostoHelperData $nostoHelperData
     * @param LoggerInterface $logger
     */
    public function __construct(
        IncludeFilters $includeFilters,
        ExcludeFilters $excludeFilters,
        NostoHelperData $nostoHelperData,
        LoggerInterface $logger
    ) {
        $this->includeFilters = $includeFilters;
        $this->excludeFilters = $excludeFilters;
        $this->nostoHelperData = $nostoHelperData;
        $this->logger = $logger;
    }

    /**
     * @param Store $store
     */
    public function init(Store $store)
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
            if ($filter instanceof Item) {
                $this->mapIncludeFilter($filter);
            }
        }
    }

    /**
     * @param Item $item
     * @throws LocalizedException
     */
    public function mapIncludeFilter(Item $item)
    {
        $filter = $item->getFilter();
        if ($filter === null) {
            return;
        }

        /** @var Attribute $attributeModel */
        $attributeModel = $filter->getData('attribute_model');
        if ($attributeModel === null) {
            return;
        }

        /** @var string $frontendInput */
        $frontendInput = $attributeModel->getData('frontend_input');
        if ($frontendInput === null) {
            return;
        }

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
                $value = (bool)$item->getData('value');
                break;
            default:
                $this->logger->debugCmp(
                    sprintf(
                        'Cannot build include filter for "%s" frontend input type',
                        $frontendInput
                    ),
                    $this
                );
                return;
        }
        try {
            $attributeCode = $attributeModel->getAttributeCode();
            if (!is_string($attributeCode)) {
                $this->logger->debugCmp(
                    sprintf(
                        'Cannot build include filter for "%s" attribute ',
                        $attributeModel->getName()
                    ),
                    $this
                );
                return;
            }
            $this->mapValueToFilter($attributeCode, $value);
        } catch (NostoException $e) {
            $this->logger->exception($e);
        }
    }

    /**
     * @param string $name
     * @param string|array $value
     * @throws NostoException
     */
    private function mapValueToFilter(string $name, $value)
    {
        if ($this->brand === $name) {
            $this->includeFilters->setBrands($this->makeArrayFromValue($name, $value));
            return;
        }

        switch (strtolower($name)) {
            case 'price':
                $this->includeFilters->setPrice(min($value), max($value));
                break;
            case 'new':
                $this->includeFilters->setFresh((bool)$value);
                break;
            default:
                $this->includeFilters->setCustomFields($name, $this->makeArrayFromValue($name, $value));
                break;
        }
    }

    /**
     * @return IncludeFilters
     */
    public function getIncludeFilters()
    {
        return $this->includeFilters;
    }

    /**
     * @return ExcludeFilters
     */
    public function getExcludeFilters()
    {
        return $this->excludeFilters;
    }

    /**
     * @param string $name
     * @param string|int|array $value
     * @return array
     * @throws NostoException
     */
    private function makeArrayFromValue($name, $value)
    {
        if (is_string($value) || is_numeric($value)) {
            $value = [$value];
        }

        if (is_array($value)) {
            return $value;
        }

        throw new NostoException(sprintf('Can not get value for filter: %s', $name));
    }
}
