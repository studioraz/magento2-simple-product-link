<?php

declare(strict_types=1);

namespace SR\SimpleProductLink\Block\Adminhtml\LinkRule;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\UrlInterface;

class GenericButton
{
    protected UrlInterface $urlBuilder;
    protected Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->urlBuilder = $context->getUrlBuilder();
    }

    public function getRuleId(): ?int
    {
        $id = $this->context->getRequest()->getParam('rule_id');
        return $id ? (int)$id : null;
    }

    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->urlBuilder->getUrl($route, $params);
    }
}
