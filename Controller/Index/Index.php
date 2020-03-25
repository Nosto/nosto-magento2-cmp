<?php
/**
 * Created by PhpStorm.
 * User: olsiqose
 * Date: 24/03/2020
 * Time: 11.52
 */

namespace Nosto\Cmp\Controller\Index;

class Index extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context)
    {
        return parent::__construct($context);
    }

    public function execute()
    {
        return "Nosto CMP Controller";
    }
}