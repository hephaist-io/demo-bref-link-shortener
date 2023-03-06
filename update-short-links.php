<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;

return new class extends EventBridgeHandler
{
    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        ['source' => $source, 'target' => $target] = $event->getDetail();
    }
};
