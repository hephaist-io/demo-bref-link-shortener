<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function Psl\Json\typed;
use function Psl\Type\non_empty_dict;
use function Psl\Type\non_empty_string;

return new class implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        $fileContent = file_get_contents('./links.json');
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

        $targetLocation = $registeredLinks[$path] ?: 'https://hephaist.io/404';

        return new Response(302, ['Location' => $targetLocation]);
    }
};
