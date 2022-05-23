<?php

namespace Nosto\Cmp\Model\Service\Merchandise;

use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;

class LastResult
{
    /** @var CategoryMerchandisingResult */
    private CategoryMerchandisingResult $lastResult;

    /**
     * @return CategoryMerchandisingResult|null
     */
    public function getLastResult(): ?CategoryMerchandisingResult
    {
        return $this->lastResult;
    }

    /**
     * @param CategoryMerchandisingResult $lastResult
     */
    public function setLastResult(CategoryMerchandisingResult $lastResult): void
    {
        $this->lastResult = $lastResult;
    }
}
