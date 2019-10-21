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

namespace Nosto\Cmp\Plugin\Catalog\Block;

use Magento\Theme\Block\Html\Pager as MagentoPager;

class Pager extends AbstractBlock
{

    /**
     * @param MagentoPager $pager
     * @param $result
     * @param $param
     * @return bool
     */
    public function afterIsPageCurrent(MagentoPager $pager, $result, $param)
    {
        if ($this->isCmpTakingOverCatalog()) {
            return $this->getCurrentPageNumber() === $param;
        }
        return $result;
    }

    /**
     * @param MagentoPager $pager
     * @param $result
     * @return array
     */
    public function afterGetFramePages(MagentoPager $pager, $result)
    {
        if ($this->isCmpTakingOverCatalog()) {
            $start = 0;
            $end = 0;
            $frameLength = $pager->getFrameLength();

            if ($this->getLastPageNumber() <= $frameLength) {
                $start = 1;
                $end = $this->getLastPageNumber();
            } else {
                $half = ceil($frameLength / 2);
                if ($this->getCurrentPageNumber() >= $half && $this->getCurrentPageNumber() <= $this->getLastPageNumber() - $half) {
                    $start = $this->getCurrentPageNumber() - $half + 1;
                    $end = $start + $frameLength - 1;
                } elseif ($this->getCurrentPageNumber() < $half) {
                    $start = 1;
                    $end = $frameLength;
                } elseif ($this->getCurrentPageNumber() > $this->getLastPageNumber() - $half) {
                    $end = $this->getLastPageNumber();
                    $start = $end - $frameLength + 1;
                }
            }
            return range($start,$end);
        }
        return $result;
    }

    /**
     * @param MagentoPager $pager
     * @param $result
     * @return bool
     */
    public function afterIsFirstPage(MagentoPager $pager, $result)
    {
        if ($this->isCmpTakingOverCatalog()) {
            return $this->getCurrentPageNumber() === 1;
        }
        return $result;
    }

    /**
     * @param MagentoPager $pager
     * @param $result
     * @return bool
     */
    public function afterIsLastPage(MagentoPager $pager, $result)
    {
        if ($this->isCmpTakingOverCatalog()) {
            return $this->getLastPageNumber() === $this->getCurrentPageNumber();
        }
        return $result;
    }

    /**
     * @param MagentoPager $pager
     * @param $result
     * @return string
     */
    public function afterGetNextPageUrl(MagentoPager $pager, $result)
    {
        if ($this->isCmpTakingOverCatalog()) {
            return $pager->getPageUrl($this->getCurrentPageNumber() + 1);
        }
        return $result;
    }

    /**
     * @param MagentoPager $pager
     * @param $result
     * @return string
     */
    public function afterGetPreviousPageUrl(MagentoPager $pager, $result)
    {
        if ($this->isCmpTakingOverCatalog()) {
            return $pager->getPageUrl($this->getCurrentPageNumber() - 1);
        }
        return $result;
    }
}
