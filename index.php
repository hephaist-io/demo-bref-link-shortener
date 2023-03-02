<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function Psl\Json\typed;
use function Psl\Type\non_empty_dict;
use function Psl\Type\non_empty_string;

return new class($_ENV['BUCKET_NAME']) implements RequestHandlerInterface
{
    public function __construct(private readonly string $bucketName) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        $client = new S3Client([
            'region' => 'eu-west-3',
            'version' => '2006-03-01'
        ]);
        $client->registerStreamWrapper();

        $fileContent = file_get_contents(sprintf('s3://%s/links.json', $this->bucketName));
        if (false === $fileContent) {
            throw new RuntimeException('The file "links.json" is missing.');
        }

        $registeredLinks = typed(
            $fileContent,
            non_empty_dict(
                non_empty_string(),
                non_empty_string()
            )
        );

        $targetLocation = $registeredLinks[$path] ?? 'https://hephaist.io/404';

        return new Response(302, ['Location' => $targetLocation]);
    }
};
