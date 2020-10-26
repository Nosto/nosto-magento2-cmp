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

namespace Nosto\Cmp\Observer\App\Action;

use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Nosto\Cmp\Model\Service\Recommendation\BatchModel;
use Nosto\Cmp\Utils\CategoryMerchandising;
use Nosto\Cmp\Utils\Debug\ServerTiming;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Cmp\Model\Service\Recommendation\SessionService;

class PostRequestAction implements ObserverInterface
{
    public const PRODUCT_DEBUG_HEADER_NAME = 'X-Nosto-Product-Ids';

    /**
     * @var HttpResponse $response
     */
    private $response;

    /** @var SessionService */
    private $session;

    /**
     * PostRequestAction constructor.
     * @param HttpResponse $response
     * @param SessionService $session
     */
    public function __construct(HttpResponse $response, SessionService $session)
    {
        $this->response = $response;
        $this->session = $session;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer) // phpcs:ignore
    {
        if (!ServerTiming::getInstance()->isEmpty()) {
            $this->response->setHeader(
                ServerTiming::HEADER_NAME,
                ServerTiming::getInstance()->build(),
                true
            );
        }

        $batchModel = $this->getBatchModel();

        $results = $observer->getData(CategoryMerchandising::DISPATCH_EVENT_KEY_RESULT);
        if ($results instanceof CategoryMerchandisingResult) {
            $this->response->setHeader(
                self::PRODUCT_DEBUG_HEADER_NAME,
                implode(',', CategoryMerchandising::parseProductIds($results)),
                true
            );

            $batchModel->setBatchToken($results->getBatchToken());
            $batchModel->setTotalCount($results->getTotalPrimaryCount());
        }

        $limit = $observer->getData(CategoryMerchandising::DISPATCH_EVENT_KEY_LIMIT);
        if (is_int($limit)) {
            $batchModel->setLastUsedLimit($limit);
        }

        $page = $observer->getData(CategoryMerchandising::DISPATCH_EVENT_KEY_PAGE);
        if (is_int($page)) {
            $batchModel->setLastFetchedPage($page);
        }

        $this->session->setBatchModel($batchModel);
    }

    /**
     * @return BatchModel
     */
    private function getBatchModel()
    {
        $batchModel = $this->session->getBatchModel();
        if ($batchModel == null) {
            return new BatchModel();
        }
        return $batchModel;
    }
}
