<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Nyholm\Psr7\Response;
use Psl\Json\Exception\DecodeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function Psl\Json\encode;
use function Psl\Json\typed;
use function Psl\Type\non_empty_string;
use function Psl\Type\non_empty_vec;

return new class implements RequestHandlerInterface
{
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

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'application/json'],
            body: encode(value: [], flags: JSON_FORCE_OBJECT)
        );
    }
};
