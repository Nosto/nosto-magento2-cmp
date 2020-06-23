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

namespace Nosto\Cmp\Plugin\Catalog\Block;

use Magento\Backend\Block\Template\Context;
use Magento\Theme\Block\Html\Pager as MagentoPager;
use Nosto\Cmp\Helper\Data as NostoCmpHelperData;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Pager extends AbstractBlock
{
    /**
     * Pager constructor.
     * @param Context $context
     * @param NostoCmpHelperData $nostoCmpHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param ParameterResolverInterface $parameterResolver
     * @param NostoLogger $logger
     */
    public function __construct(
        Context $context,
        NostoCmpHelperData $nostoCmpHelperData,
        NostoHelperAccount $nostoHelperAccount,
        ParameterResolverInterface $parameterResolver,
        NostoLogger $logger
    ) {
        parent::__construct($context, $parameterResolver, $nostoCmpHelperData, $nostoHelperAccount, $logger);
    }

    /**
     * @param MagentoPager $pager
     * @param $result
     * @param $param
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterIsPageCurrent( // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        MagentoPager $pager,
        $result,
        $param
    ) {
        if ($this->isCmpTakingOverCatalog()) {
            return $this->getCurrentPageNumber() === (int)$param;
        }
        return $result;
    }

    /**
     * Get page number list to display at the user
     * In case of many pages it will not display all page numbers
     * but only a limited range
     *
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

            // If total number of pages is smaller than frameLength, display them all
            $lastPageNum = $this->getLastPageNumber();
            if ($lastPageNum <= $frameLength) {
                $start = 1;
                $end = $lastPageNum;
                //else display only as much as frameLength
            } else {
                $half = ceil($frameLength / 2);
                $curPageNum = $this->getCurrentPageNumber();
                if ($curPageNum >= $half
                    && $curPageNum <= $lastPageNum - $half) {
                    $start = $curPageNum - $half + 1;
                    $end = $start + $frameLength - 1;
                } elseif ($curPageNum < $half) {
                    $start = 1;
                    $end = $frameLength;
                } elseif ($curPageNum > $lastPageNum - $half) {
                    $end = $lastPageNum;
                    $start = $end - $frameLength + 1;
                }
            }
            return range($start, $end);
        }
        return $result;
    }

    /**
     * @param MagentoPager $pager
     * @param $result
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterIsFirstPage( // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        MagentoPager $pager,
        $result
    ) {
        if ($this->isCmpTakingOverCatalog()) {
            return $this->getCurrentPageNumber() === 1;
        }
        return $result;
    }

    /**
     * @param MagentoPager $pager
     * @param $result
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterIsLastPage(// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        MagentoPager $pager,
        $result
    ) {
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
            return $pager->getPageUrl((string)($this->getCurrentPageNumber() + 1));
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
            return $pager->getPageUrl((string)($this->getCurrentPageNumber() - 1));
        }
        return $result;
    }
}
