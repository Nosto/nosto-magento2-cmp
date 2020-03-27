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

namespace Nosto\Cmp\Controller\Index;

use Magento\Framework\View\LayoutFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Nosto\Cmp\Block\CategoryMerchandising;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Action\Action;
use Nosto\Cmp\Model\Service\Merchandising\Result;
use Nosto\Cmp\Model\Service\Merchandising\ResultBuilder;

class Index extends Action
{
    /** @var LayoutFactory  */
    private $layoutFactory;

    /** @var PageFactory  */
    private $pageFactory;

    /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection  */
    private $productCollection;

    /** @var JsonFactory  */
    private $jsonFactory;

    /** @var Http  */
    private $request;

    /** @var ResultBuilder */
    private $resultBuilder;

    /**
     * Index constructor.
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param CollectionFactory $collectionFactory
     * @param JsonFactory $jsonFactory
     * @param Http $request
     * @param ResultBuilder $resultBuilder
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        CollectionFactory $collectionFactory,
        JsonFactory $jsonFactory,
        Http $request,
        ResultBuilder $resultBuilder
    ) {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->jsonFactory = $jsonFactory;
        $this->productCollection = $collectionFactory->create();
        $this->request = $request;
        $this->resultBuilder = $resultBuilder;
    }

    public function execute()
    {
        $this->resultBuilder
            ->setNostoCustomerId($this->request->get('customer'))
            ->setLimit($this->request->get('limit'))
            ->setCurrentPage($this->request->get('curPage'))
            ->setCategory($this->request->get('category'));
        $ids = $this->getResults($this->resultBuilder->build());

        $this->alterCollection();
        // get the custom list block and add our collection to it
        /** @var CategoryMerchandising $list */
        $list = $this->pageFactory->create()
            ->getLayout()
            ->getBlock('nosto.products.list');
        $list->setProductCollection($this->productCollection);

        $result = $this->jsonFactory->create();
        return $result->setData(['template' => $list->toHtml()]);
    }

    /**
     * @param array $ids
     */
    private function alterCollection($ids){
        // obtain product collection.
        $this->productCollection->addIdFilter(14); // do some filtering
        $this->productCollection->addFieldToSelect('*');
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function dummyStore(){
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Store\Model\StoreManagerInterface $manager */
        $manager = $om->get('Magento\Store\Model\StoreManagerInterface');
        return $manager->getStore(1);
    }

    public function getResults(Result $result) {
        //Fetch CMP results
        return $result->getSortingOrderResults($this->dummyStore());
    }
}