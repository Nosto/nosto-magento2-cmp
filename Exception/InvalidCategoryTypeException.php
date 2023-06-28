<?php

namespace Nosto\Cmp\Exception;

use Magento\Store\Model\Store;

class InvalidCategoryTypeException extends CmpException
{
    private const DEFAULT_MESSAGE = "Category is not a valid default Magento category type.";

    /**
     * @param Store $store
     */
    public function __construct(Store $store)
    {
        parent::__construct($store, self::DEFAULT_MESSAGE);
    }

}
