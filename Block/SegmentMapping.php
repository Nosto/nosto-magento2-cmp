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

namespace Nosto\Cmp\Block;

use Exception;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Nosto;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Customer\Customer as NostoCustomer;

class SegmentMapping extends Template
{
    const COOKIE_CATEGORY_MAP = "n_cmp_mapping";

    const COOKIE_SEGMENT_MAP = "n_cmp_indexes";

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var CookieManagerInterface */
    private $cookieManager;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var NostoLogger */
    private $logger;

    public function __construct(
        StoreManagerInterface $storeManager,
        NostoHelperAccount $nostoHelperAccount,
        CookieManagerInterface $cookieManager,
        Context $context,
        NostoLogger $logger
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->cookieManager = $cookieManager;
        $this->logger = $logger;
    }

    /**
     * Return Nosto merchant id
     * @return null|string
     */
    public function getNostoAccount()
    {
        try {
            $store = $this->storeManager->getStore();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage() . 'Could not get Nosto account ID');
            return null;
        }
        /** @var Store $store */
        return $this->nostoHelperAccount->getAccountName($store); //@phan-suppress-current-line PhanTypeMismatchArgument
    }

    /**
     * Return customer id
     * @return null|string
     */
    public function getCustomerId()
    {
        return $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME);
    }

    /**
     * Return the mapping cookie name
     * @return string
     */
    public function getCategoryMappingCookieName()
    {
        return self::COOKIE_CATEGORY_MAP;
    }

    /**
     * Return the mapping cookie name
     * @return string
     */
    public function getSegmentMappingCookieName()
    {
        return self::COOKIE_SEGMENT_MAP;
    }

    /**
     * @return mixed
     */
    public function getNostoBaseUrl()
    {
        return Nosto::getBaseUrl();
    }
}
