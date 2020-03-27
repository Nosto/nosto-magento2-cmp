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

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Nosto\Cmp\Block\CategoryMerchandising;
use Nosto\Cmp\Model\Service\Merchandising\Result;
use Nosto\Cmp\Model\Service\Merchandising\ResultBuilder;
use Nosto\Cmp\Plugin\Catalog\Model\Product as NostoProductPlugin;
use Nosto\Cmp\Utils\Debug\Product as ProductDebug;
use Nosto\Helper\ArrayHelper as NostoHelperArray;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Zend_Db_Expr;

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

    /** @var NostoLogger */
    private $logger;

    /**
     * Index constructor.
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param CollectionFactory $collectionFactory
     * @param JsonFactory $jsonFactory
     * @param Http $request
     * @param ResultBuilder $resultBuilder
     * @param NostoLogger $logger
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        CollectionFactory $collectionFactory,
        JsonFactory $jsonFactory,
        Http $request,
        ResultBuilder $resultBuilder,
        NostoLogger $logger
    ) {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->jsonFactory = $jsonFactory;
        $this->productCollection = $collectionFactory->create();
        $this->request = $request;
        $this->resultBuilder = $resultBuilder;
        $this->logger = $logger;
    }

    public function execute()
    {
        $this->resultBuilder->setNostoCustomerId($this->request->get('customer'));
        $this->resultBuilder->setLimit($this->request->get('limit'));
        $this->resultBuilder->setCurrentPage($this->request->get('currPage'));
        $this->resultBuilder->setCategory($this->request->get('category'));
        $merchResult = $this->getResults($this->resultBuilder->build());


        $this->alterCollection($merchResult);
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
        return $result->getCmpResult($this->dummyStore());
    }

    /**
     * @param array $ids
     */
    private function alterCollection(CategoryMerchandisingResult $result){
        // obtain product collection.
        $this->productCollection->addFieldToSelect('*');

        $nostoProductIds = $this->parseProductIds($result);
        if (!empty($nostoProductIds)
            && NostoHelperArray::onlyScalarValues($nostoProductIds)
        ) {
            $this->setTotalProducts($result->getTotalPrimaryCount());
            ProductDebug::getInstance()->setProductIds($nostoProductIds);
            $nostoProductIds = array_reverse($nostoProductIds);
            $this->sortByProductIds($this->productCollection, $nostoProductIds);
            $this->whereInProductIds($this->productCollection, $nostoProductIds);
            $this->addTrackParamToProduct($this->productCollection, $result->getTrackingCode(), $nostoProductIds);
        } else {
            $this->logger->info(sprintf(
                "CMP result is empty for category: %s",
                $this->getCurrentCategory($store)
            ));
        }
    }

    /**
     * @param ProductCollection $collection
     * @param array $nostoProductIds
     */
    private function sortByProductIds(ProductCollection $collection, array $nostoProductIds)
    {
        $select = $collection->getSelect();
        $zendExpression = [
            new Zend_Db_Expr('FIELD(e.entity_id,' . implode(',', $nostoProductIds) . ') DESC'),
            new Zend_Db_Expr($this->getSecondarySort())
        ];
        $select->order($zendExpression);
    }

    /**
     * @param ProductCollection $collection
     * @param array $nostoProductIds
     */
    private function whereInProductIds(ProductCollection $collection, array $nostoProductIds)
    {
        $select = $collection->getSelect();
        $zendExpression = new Zend_Db_Expr(
            'e.entity_id IN (' . implode(',', $nostoProductIds ) . ')'
        );
        $select->where($zendExpression);
    }

    /**
     * @param CategoryMerchandisingResult $result
     * @return array
     */
    private function parseProductIds(CategoryMerchandisingResult $result)
    {
        $productIds = [];
        try {
            foreach ($result->getResultSet() as $item) {
                if ($item->getProductId() && is_numeric($item->getProductId())) {
                    $productIds[] = $item->getProductId();
                }
            }
        } catch (Exception $e) {
            $this->logger->exception($e);
        }

        return $productIds;
    }

    /**
     * @param ProductCollection $collection
     * @param $trackCode
     * @param array $nostoProductIds
     */
    private function addTrackParamToProduct(ProductCollection $collection, $trackCode, array $nostoProductIds)
    {
        $collection->each(static function ($product) use ($nostoProductIds, $trackCode) {
            /* @var Product $product */
            if (in_array($product->getId(), $nostoProductIds, true)) {
                $product->setData(NostoProductPlugin::NOSTO_TRACKING_PARAMETER_NAME, $trackCode);
            }
        });
    }

    /**
     * Returns the secondary sort defined by the merchant
     *
     * @return string
     */
    private function getSecondarySort()
    {
        return 'cat_index_position ASC'; // ToDo - must be selectable by the merchant
    }
}