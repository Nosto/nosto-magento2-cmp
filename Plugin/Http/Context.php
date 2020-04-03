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

namespace Nosto\Cmp\Plugin\Http;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Http\Context as MagentoContext;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\App\Request\Http;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Category;
use Nosto\Tagging\Model\Service\Product\Category\DefaultCategoryService as CategoryBuilder;
use Magento\Store\Model\StoreManagerInterface;

class Context
{
    /** @var CookieManagerInterface  */
    private $cookieManager;

    /** @var Http  */
    private $request;

    /** @var CategoryFactory  */
    private $categoryFactory;

    /** @var String */
    private $categoryString;

    /** @var CategoryBuilder  */
    private $categoryBuilder;

    /** @var StoreManagerInterface  */
    private $storeManager;

    public function __construct(
        Session $customerSession,
        CookieManagerInterface $cookieManager,
        CategoryFactory $categoryFactory,
        CategoryBuilder $categoryBuilder,
        StoreManagerInterface $storeManager,
        Http $request
    ) {
        $this->customerSession = $customerSession;
        $this->cookieManager = $cookieManager;
        $this->categoryFactory = $categoryFactory;
        $this->categoryBuilder = $categoryBuilder;
        $this->storeManager = $storeManager;
        $this->request = $request;
    }
    /**
     * \Magento\Framework\App\Http\Context::getVaryString is used by Magento to retrieve unique identifier for selected context,
     * so this is a best place to declare custom context variables
     */
    function beforeGetVaryString(MagentoContext $subject)
    {
        if ($this->isCategoryPage() &&
            $this->request->getParam('product_list_order') &&
            $this->request->getParam('product_list_order') === 'nosto-personalized') {

            $variation = $this->getForcedSegmentsFromCookie();
            $subject->setValue('CONTEXT_NOSTO', $variation, $defaultValue = "");
        }
            return $subject;
    }

    private function getForcedSegmentsFromCookie() {
        $cookie = $this->cookieManager->getCookie('nosto_debug');
        $decoded = json_decode($cookie);
        if ($decoded->fs && is_array($decoded->fs)) {
            return implode("-", $decoded->fs);
        }
    }

    /**
     * @return bool
     */
    private function isCategoryPage() {
        $category = $this->getCategory();
        if ($category) {
            $this->categoryString = $this->categoryBuilder->getCategory($category, $this->storeManager->getStore());
            return true;
        }
        return false;
    }

    /**
     * @return bool|Category
     */
    private function getCategory() {
        $categoryFactory = $this->categoryFactory->create();
        $urlPath = $this->getUrlPAth();
        if (!is_string($urlPath)) {
            return false;
        }
        return $categoryFactory->loadByAttribute('url_path', $urlPath);
    }

    /**
     * @return string
     */
    private function getUrlPAth() {
        $path = $this->request->getUri()->getPath();

        //Remove leading slash
        $path = substr($path, 1);

        //Remove . ending
        $path = explode(".", $path)[0];

        return $path;
    }
}
