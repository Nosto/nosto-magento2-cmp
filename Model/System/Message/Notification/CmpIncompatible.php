<?php
/**
 * Copyright (c) 2022, Nosto Solutions Ltd
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

namespace Nosto\Cmp\Model\System\Message\Notification;

use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\Phrase;
use Nosto\Cmp\Helper\SearchEngine;

class CmpIncompatible implements MessageInterface
{
    /**
     * @var SearchEngine
     */
    private SearchEngine $searchEngine;

    /**
     * @var mixed
     */
    private $message;

    /**
     * Messages constructor.
     * @param SearchEngine $searchEngine
     */
    public function __construct(
        SearchEngine $searchEngine
    ) {
        $this->searchEngine = $searchEngine;
    }

    /**
     * @return Phrase
     */
    public function getText()
    {
        return __($this->message);
    }

    /**
     * @return string
     */
    public function getIdentity()
    {
        return sha1('Nosto_Cmp_Incompatibility_Notification');
    }

    /**
     * @return bool
     */
    public function isDisplayed()
    {
        if (!$this->searchEngine->isMysql()) {
            return false;
        }

        $this->message =
            'Nosto CMP is not compatible with MySQL search engine. '
            . 'Please downgrade to version 3 of the module or switch '
            . 'your search engine to Elasticsearch.'
        ;
        return true;
    }

    /**
     * @return int
     */
    public function getSeverity()
    {
        return MessageInterface::SEVERITY_CRITICAL;
    }
}
