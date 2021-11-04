<?php /** @noinspection PhpUnused */

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

namespace Nosto\Cmp\Block;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Cmp\Model\Service\Category\CategoryMappingServiceInterface;
use Nosto\Tagging\Logger\Logger;

class CategoryMapping extends Template
{

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var CategoryMappingServiceInterface  */
    private $mappingService;

    /** @var Logger */
    private $logger;

    /**
     * @param StoreManagerInterface $storeManager
     * @param CategoryMappingServiceInterface $mappingService
     * @param Context $context
     * @param Logger $logger
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CategoryMappingServiceInterface $mappingService,
        Context $context,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->mappingService = $mappingService;
        $this->logger = $logger;
    }

    /**
     * @return false|string
     */
    public function getCategoryMap()
    {

        $mapping = '';
        try {
            $store = $this->storeManager->getStore();
            if ($store instanceof Store) {
                $mapping = $this->mappingService->getCategoryMapping($store);
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->exception($e);
        }

        return $mapping;
    }
}
