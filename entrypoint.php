<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Aws\DynamoDb\DynamoDbClient;
use Aws\EventBridge\EventBridgeClient;
use Hidehalo\Nanoid\Client;
use Nyholm\Psr7\Response;
use Psl\Json\Exception\DecodeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Uid\Uuid;
use function Psl\Json\encode;
use function Psl\Json\typed;
use function Psl\Type\non_empty_string;
use function Psl\Type\non_empty_vec;

return new class($_ENV['TABLE_NAME'], $_ENV['DOMAIN_NAME']) implements RequestHandlerInterface
{
    private const NS_ID = '7c1a90e3-de46-48cb-811b-06d42dde7524';

    public function __construct(
        private readonly string $linksTableName,
        private readonly string $domainName
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $errors = [];
        try {
            $links = typed(
                $request->getBody()->getContents(),
                non_empty_vec(
                    non_empty_string()
                )
            );
        } catch (DecodeException $error) {
            return new Response(
                status: 400,
                headers: ['Content-Type' => 'application/json'],
                body: encode(['errors' => [$error->getMessage()]])
            );
        }

        foreach($links as $link) {
            if (false === filter_var($link, FILTER_VALIDATE_URL)) {
                $errors[] = sprintf('Link "%s" is not a valid URL.', $link);
            }
        }

        if (!empty($errors)) {
            return new Response(
                status: 400,
                headers: ['Content-Type' => 'application/json'],
                body: encode(['errors' => $errors])
            );
        }

        $dbClient = new DynamoDbClient(['region' => 'eu-west-3', 'version' => '2012-08-10']);
        $shortIdGenerator = new Client(size: 8);

        $generatedLinks = [];
        $putItemRequests = [];
        $events = [];
        foreach ($links as $target) {
            // Générer le lien raccourci
            $source = sprintf('%s/%s', $this->domainName, $shortIdGenerator->generateId());

            // Générer un UUID prédictible à partir du lien de destination
            $linkId = Uuid::v5(namespace: Uuid::fromString(self::NS_ID), name: $target);

            // Préparer la requête d'enregistrement dans DynamoDB
            $putItemRequests[] = [
                'PutRequest' => [
                    'Item' => [
                        // Champs pré-configurés par Lift
                        'PK' => ['S' => $linkId], // Clé primaire
                        'SK' => ['S' => $target], // Clé de tri

                        // Attribut de l'enregistrement
                        'linkId' => ['S' => $linkId],
                        'target' => ['S' => $target],
                        'source' => ['S' => $source]
                    ]
                ]
            ];

            $events[] = [
                'Source' => 'demo-link-shortener.entrypoint',
                'DetailType' => 'LinkWasRegistered',
                'Detail' => encode([
                    'source' => $source,
                    'target' => $target
                ])
            ];
            $generatedLinks[$target] = $source;
        }

        // Écrire les enregistrements dans DynamoDB
        $dbClient->batchWriteItem([
            'RequestItems' => [
                $this->linksTableName => $putItemRequests
            ]
        ]);

        $eventBridgeClient = new EventBridgeClient(['region' => 'eu-west-3', 'version' => '2015-10-07']);
        $eventBridgeClient->putEvents(['Entries' => $events]);

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'application/json'],
            body: encode(value: $generatedLinks, flags: JSON_FORCE_OBJECT)
        );
    }
};
