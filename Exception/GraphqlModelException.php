<?php

namespace Nosto\Cmp\Exception;

use Magento\Store\Model\Store;

class GraphqlModelException extends CmpException
{
    const DEFAULT_MESSAGE = "Could not get graphql model from session";

    /**
     * @param Store $store
     */
    public function __construct(Store $store)
    {
        parent::__construct($store, self::DEFAULT_MESSAGE);
    }

}
