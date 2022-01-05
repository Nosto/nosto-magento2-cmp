<?php

namespace Nosto\Cmp\Model\Service\Merchandise;

use Nosto\Cmp\Model\Merchandise\MerchandiseRequestParams;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;

interface MerchandiseServiceInterface
{
    /**
     * @param MerchandiseRequestParams $requestParams
     * @return CategoryMerchandisingResult
     */
    public function getMerchandiseResults(MerchandiseRequestParams $requestParams): CategoryMerchandisingResult;
}
