<?php

namespace Nosto\Cmp\Model\Service\Merchandise;

use Nosto\Cmp\Helper\Data as CmpHelperData;
use Nosto\Cmp\Model\Merchandise\MerchandiseRequestParams;
use Nosto\Cmp\Utils\Traits\LoggerTrait;
use Nosto\Request\Http\HttpRequest;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger;

class TracingMerchandiseService implements MerchandiseServiceInterface
{
    use LoggerTrait {
        LoggerTrait::__construct as loggerTraitConstruct; // @codingStandardsIgnoreLine
    }

    /** @var MerchandiseServiceInterface */
    private $merchandiseService;

    /** @var NostoHelperData */
    private $nostoHelperData;

    /** @var CmpHelperData */
    private $cmpHelperData;

    /**
     * @param MerchandiseServiceInterface $merchandiseService
     * @param NostoHelperData $nostoHelperData
     * @param CmpHelperData $cmpHelperData
     */
    public function __construct(
        MerchandiseServiceInterface $merchandiseService,
        NostoHelperData $nostoHelperData,
        CmpHelperData $cmpHelperData,
        Logger $logger
    ) {
        $this->loggerTraitConstruct(
            $logger
        );
        $this->merchandiseService = $merchandiseService;
        $this->nostoHelperData = $nostoHelperData;
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
        $this->traceResultSet($requestParams, $result);

        return $result;
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
