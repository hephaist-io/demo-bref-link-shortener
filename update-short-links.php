<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use function Psl\Json\encode;
use function Psl\Json\typed;
use function Psl\Type\dict;
use function Psl\Type\non_empty_string;

return new class($_ENV['BUCKET_NAME']) extends EventBridgeHandler
{
    public function __construct(private readonly string $bucketName) {}

    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        ['source' => $source, 'target' => $target] = $event->getDetail();

        $client = new S3Client(['region' => 'eu-west-3', 'version' => '2006-03-01']);
        $client->registerStreamWrapper();

        $filePath = sprintf('s3://%s/links.json', $this->bucketName);
        $fileContent = file_get_contents($filePath);
        if (false === $fileContent) {
            $fileContent = '{}'; // Le fichier n'existe pas encore, on initialise à vide notre liste de liens.
        }

        $registeredLinks = typed(
            $fileContent,
            dict(
                non_empty_string(),
                non_empty_string()
            )
        );

        $registeredLinks[parse_url($source, PHP_URL_PATH)] = $target;

        file_put_contents($filePath, encode(value: $registeredLinks, flags: JSON_FORCE_OBJECT));
    }
};
