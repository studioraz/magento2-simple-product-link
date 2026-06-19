<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Observer;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class InvalidateCacheOnRuleChange implements ObserverInterface
{
    private TypeListInterface $typeList;

    public function __construct(TypeListInterface $typeList)
    {
        $this->typeList = $typeList;
    }

    public function execute(Observer $observer): void
    {
        $this->typeList->cleanType('full_page');
    }
}
