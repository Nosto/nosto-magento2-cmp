<?php

namespace Nosto\Cmp\Model\Service\Merchandise;

use Nosto\Cmp\Helper\Data as CmpHelperData;
use Nosto\Cmp\Model\Merchandise\MerchandiseRequestParams;
use Nosto\Cmp\Utils\Debug\ServerTiming;
use Nosto\Cmp\Utils\Traits\LoggerTrait;
use Nosto\Request\Http\HttpRequest;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger;
use Magento\Framework\App\Response\Http as HttpResponse;

class TracingMerchandiseService implements MerchandiseServiceInterface
{
    public const PRODUCT_DEBUG_HEADER_NAME = 'X-Nosto-Product-Ids';

    use LoggerTrait {
        LoggerTrait::__construct as loggerTraitConstruct; // @codingStandardsIgnoreLine
    }

    /** @var MerchandiseServiceInterface */
    private MerchandiseServiceInterface $merchandiseService;

    /** @var NostoHelperData */
    private NostoHelperData $nostoHelperData;

    /** @var HttpResponse */
    private HttpResponse $response;

    /** @var CmpHelperData */
    private CmpHelperData $cmpHelperData;

    /**
     * @param MerchandiseServiceInterface $merchandiseService
     * @param NostoHelperData $nostoHelperData
     * @param HttpResponse $response
     * @param CmpHelperData $cmpHelperData
     * @param Logger $logger
     */
    public function __construct(
        MerchandiseServiceInterface $merchandiseService,
        NostoHelperData $nostoHelperData,
        HttpResponse $response,
        CmpHelperData $cmpHelperData,
        Logger $logger
    ) {
        $this->loggerTraitConstruct(
            $logger
        );
        $this->merchandiseService = $merchandiseService;
        $this->nostoHelperData = $nostoHelperData;
        $this->response = $response;
        $this->cmpHelperData = $cmpHelperData;
    }

    /**
     * @param MerchandiseRequestParams $requestParams
     * @return CategoryMerchandisingResult
     */
    public function getMerchandiseResults(MerchandiseRequestParams $requestParams): CategoryMerchandisingResult
    {
        HttpRequest::buildUserAgent(
            'Magento',
            $this->nostoHelperData->getPlatformVersion(),
            "CMP_" . $this->cmpHelperData->getModuleVersion()
        );

        $result = $this->merchandiseService->getMerchandiseResults($requestParams);

        $this->addResponseHeaderParam($result);
        $this->traceResultSet($requestParams, $result);

        return $result;
    }

    /**
     * @param CategoryMerchandisingResult $result
     */
    private function addResponseHeaderParam(CategoryMerchandisingResult $result)
    {
        //Timing header
        if (!ServerTiming::getInstance()->isEmpty()) {
            $this->response->setHeader(
                ServerTiming::HEADER_NAME,
                ServerTiming::getInstance()->build(),
                true
            );
        }

        //Nosto products ids header
        if ($result instanceof CategoryMerchandisingResult) {
            $this->response->setHeader(
                self::PRODUCT_DEBUG_HEADER_NAME,
                implode(',', $result->parseProductIds()),
                true
            );
        }
    }

    /**
     * @param MerchandiseRequestParams $requestParams
     * @param CategoryMerchandisingResult $result
     */
    private function traceResultSet(MerchandiseRequestParams $requestParams, CategoryMerchandisingResult $result)
    {
        $this->trace(
            'Got %d / %d (total) product ids from Nosto CMP for category "%s", using page num: %d, using limit: %d',
            [
                $result->getResultSet()->count(),
                $result->getTotalPrimaryCount(),
                $requestParams->getCategory(),
                $requestParams->getPageNumber(),
                $requestParams->getLimit()
            ]
        );
    }
}
