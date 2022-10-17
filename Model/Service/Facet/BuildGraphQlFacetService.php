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

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Nosto\Cmp\Model\Facet\Facet;
use Nosto\Cmp\Utils\Traits\LoggerTrait;
use Nosto\Operation\Recommendation\ExcludeFilters;
use Nosto\Operation\Recommendation\IncludeFilters;
use Nosto\Tagging\Logger\Logger;

class BuildGraphQlFacetService
{
    use LoggerTrait {
        LoggerTrait::__construct as loggerTraitConstruct; // @codingStandardsIgnoreLine
    }

    /**
     * @var ProductAttributeRepositoryInterface
     */
    private ProductAttributeRepositoryInterface $productAttributeRepository;

    /**
     * BuildGraphQlFacetService constructor.
     * @param ProductAttributeRepositoryInterface $productAttributeRepository
     * @param Logger $logger
     */
    public function __construct(
        ProductAttributeRepositoryInterface $productAttributeRepository,
        Logger $logger
    ) {
        $this->loggerTraitConstruct(
            $logger
        );
        $this->productAttributeRepository = $productAttributeRepository;
    }

    /**
     * @param array $requestData
     * @return Facet
     */
    public function getFacets(array $requestData): Facet
    {
        $includeFilters = new IncludeFilters();
        $excludeFilters = new ExcludeFilters();

        foreach ($requestData['filters'] as $filter) {
            if ($filter['name'] === 'category_filter' || // Skip visibility and category filters
                $filter['name'] === 'visibility_filter'
            ) {
                continue;
            } elseif ($filter['name'] === 'price_filter') { // Price filters
                $includeFilters->setPrice(
                    isset($filter['from']) ? $filter['from'] : null,
                    isset($filter['to']) ? $filter['to'] : null
                );
            } else { // Custom field filters
                try {
                    $attributeCode = $filter['field'];
                    $filterValues = $filter['value'];
                    $attribute = $this->productAttributeRepository->get($attributeCode);
                    $customFieldValues = [];

                    if (is_string($filterValues)) { // eq attribute
                        /** @phan-suppress-next-next-line PhanUndeclaredMethod */
                        /** @noinspection PhpUndefinedMethodInspection */
                        $customFieldValues = [$attribute->getSource()->getOptionText($filterValues)];
                    } else { // in attribute
                        foreach ($filterValues as $value) {
                            /** @phan-suppress-next-next-line PhanUndeclaredMethod */
                            /** @noinspection PhpUndefinedMethodInspection */
                            $customFieldValues[] = $attribute->getSource()->getOptionText($value);
                        }
                    }

                    $includeFilters->setCustomFields(
                        $attributeCode,
                        $customFieldValues
                    );
                } catch (NoSuchEntityException $e) {
                    $this->logger->exception($e);
                }
            }
        }

        return new Facet($includeFilters, $excludeFilters);
    }
}
