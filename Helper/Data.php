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

namespace Nosto\Cmp\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;

class Data extends AbstractHelper
{
    const MODULE_NAME = 'Nosto_Cmp';

    /** @var NostoHelperScope */
    private NostoHelperScope $nostoHelperScope;

    /**
     * Path to the configuration object that stores category sorting
     */
    const XML_PATH_CATEGORY_SORTING = 'nosto_cmp/flags/category_sorting';

    /**
     * Path to the configuration object that stores categories mapping
     */
    const XML_PATH_CATEGORY_MAPPING = 'nosto_cmp/flags/map_all_categories';

    /**
     * Path to the configuration object that stores fallback sorting
     */
    const XML_PATH_FALLBACK_SORTING = 'nosto_cmp/flags/fallback_sorting';

    /**
     * Path to the configuration object that stores the max limit for products
     */
    const XML_PATH_CATEGORY_MAX_PRODUCT_LIMIT = 'nosto_cmp/limit/max_products';

    /** @var ModuleListInterface */
    private ModuleListInterface $moduleList;

    /**
     * Data constructor.
     * @param Context $context
     * @param NostoHelperScope $nostoHelperScope
     * @param ModuleListInterface $moduleList
     * @noinspection PhpUnused
     */
    public function __construct(
        Context $context,
        NostoHelperScope $nostoHelperScope,
        ModuleListInterface $moduleList
    ) {
        parent::__construct($context);
        $this->nostoHelperScope = $nostoHelperScope;
        $this->moduleList = $moduleList;
    }

    /**
     * Returns if category sorting is enabled
     *
     * @param Store|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isCategorySortingEnabled(Store $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_CATEGORY_SORTING, $store);
    }

    /**
     * Returns if mapping of all categories is enabled
     *
     * @param Store|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isAllCategoriesMapEnabled(Store $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_CATEGORY_MAPPING, $store);
    }

    /**
     * Returns the fallback sorting
     *
     * @param Store|null $store the store model or null.
     * @return string
     */
    public function getFallbackSorting(Store $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_FALLBACK_SORTING, $store);
    }

    /**
     * Returns max product limit
     *
     * @param Store|null $store the store model or null.
     * @return integer
     */
    public function getMaxProductLimit(Store $store = null)
    {
        return (int)$this->getStoreConfig(self::XML_PATH_CATEGORY_MAX_PRODUCT_LIMIT, $store);
    }

    /**
     * @param string $path
     * @param Store|null $store
     * @return mixed|null
     */
    public function getStoreConfig(string $path, Store $store = null)
    {
        if ($store === null) {
            $store = $this->nostoHelperScope->getStore(true);
        }
        return $store->getConfig($path);
    }

    /**
     * Returns the module version number of the Nosto CMP module.
     *
     * @return string the module's version
     */
    public function getModuleVersion()
    {
        $nostoCmpModule = $this->moduleList->getOne(self::MODULE_NAME);
        if (!empty($nostoCmpModule['setup_version'])) {
            return $nostoCmpModule['setup_version'];
        }
        return 'unknown';
    }
}
