<?php


namespace Nosto\Cmp\Model\Filter;

use Nosto\Operation\Recommendation\ExcludeFilters;
use Nosto\Operation\Recommendation\IncludeFilters;

interface FiltersInterface
{
    /**
     * @return IncludeFilters
     */
    public function getIncludeFilters();

    /**
     * @return ExcludeFilters
     */
    public function getExcludeFilters();

}
