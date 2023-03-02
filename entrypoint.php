<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

return new class implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(
            status: 200,
            headers: ['Content-Type' => 'application/json'],
            body: \Psl\Json\encode(value: [], flags: \JSON_FORCE_OBJECT)
        );
    }
};
