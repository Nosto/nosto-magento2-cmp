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

namespace Nosto\Cmp\Model\Service\Facet;

use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\LayeredNavigation\Block\Navigation\State;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Cmp\Model\Facet\Facet;
use Nosto\NostoException;
use Nosto\Operation\Recommendation\ExcludeFilters;
use Nosto\Operation\Recommendation\IncludeFilters;
use Nosto\Cmp\Logger\LoggerInterface;

class BuildWebFacetService implements BuildFacetService
{

    /** @var State */
    private $state;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var LoggerInterface */
    private $logger;

    /**
     * BuildWebFacetService constructor.
     * @param StoreManagerInterface $storeManager
     * @param State $state
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        State $state,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->state = $state;
        $this->logger = $logger;
    }

    public function getFacets(): Facet
    {
        $includeFilters = new IncludeFilters();
        $excludeFilters = new ExcludeFilters();

        try {
            $this->populateFilters($includeFilters);
        } catch (NoSuchEntityException $e) {

        }
        return new Facet($includeFilters, $excludeFilters);
    }

    private function populateFilters(IncludeFilters &$includeFilters): void
    {
        $filters = $this->state->getActiveFilters();
        foreach ($filters as $filter) {

        }
    }

    /**
     * @param Item $item
     * @throws LocalizedException
     */
    public function mapIncludeFilter(IncludeFilters &$includeFilters, Item $item)
    {

        //\Magento\CatalogSearch\Model\Layer\Filter\Category
        if ($item->getFilter() instanceof \Magento\CatalogSearch\Model\Layer\Filter\Category) {
            $categoryId = $item->getData('value');

        }

        //Magento\CatalogSearch\Model\Layer\Filter\Attribute
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
            $this->mapValueToFilter($includeFilters,$attributeCode, $value);
        } catch (NostoException $e) {
            $this->logger->exception($e);
        }
    }

    /**
     * @param string $name
     * @param string|array $value
     * @throws NostoException
     */
    private function mapValueToFilter(IncludeFilters &$includeFilters, string $name, $value)
    {
        if ($this->brand === $name) {
            $includeFilters->setBrands($this->makeArrayFromValue($name, $value));
            return;
        }

        switch (strtolower($name)) {
            case 'price':
                $includeFilters->setPrice(min($value), max($value));
                break;
            case 'new':
                $includeFilters->setFresh((bool)$value);
                break;
            default:
                $includeFilters->setCustomFields($name, $this->makeArrayFromValue($name, $value));
                break;
        }
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

        if (is_bool($value)) {
            // bool Yes/No attributes are stored as text in Nosto
            $value = $value ? "Yes" : "No";
            $value = [$value];
        }

        if (is_array($value)) {
            return $value;
        }

        throw new NostoException(sprintf('Can not get value for filter: %s', $name));
    }
}
