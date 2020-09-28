<?php


namespace Nosto\Cmp\Model\Filter;


use Nosto\Operation\Recommendation\ExcludeFilters;
use Nosto\Operation\Recommendation\IncludeFilters;

class GraphQlFilters implements FiltersInterface
{
    /** @var IncludeFilters */
    private $includeFilters;

    /** @var ExcludeFilters */
    private $excludeFilters;

    /** @var array */
    private $requestData;

    /**
     * GraphQlFilters constructor.
     * @param IncludeFilters $includeFilters
     * @param ExcludeFilters $excludeFilters
     */
    public function __construct(IncludeFilters $includeFilters, ExcludeFilters $excludeFilters)
    {
        $this->includeFilters = $includeFilters;
        $this->excludeFilters = $excludeFilters;
    }


    /**
     * @inheritDoc
     */
    public function getIncludeFilters()
    {
        return $this->includeFilters;
    }

    /**
     * @inheritDoc
     */
    public function getExcludeFilters()
    {
        return $this->excludeFilters;
    }

    /**
     * @param array $requestData
     */
    public function setRequestData(array $requestData) {
        $this->requestData = $requestData;

        if (isset($requestData['filters']['price_filter'])) {
            $priceFilters = $requestData['filters']['price_filter'];
            $this->includeFilters->setPrice(
                isset($priceFilters['from']) ? $priceFilters['from'] : null,
                isset($priceFilters['to']) ? $priceFilters['to'] : null
            );
        }
    }
}
