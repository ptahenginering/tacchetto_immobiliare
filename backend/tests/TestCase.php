<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['JWT_SECRET'] = 'test-secret-0123456789abcdef0123456789abcdef';
    }

    protected function request(string $method, string $path, array $body = [], array $headers = []): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }
        return $request;
    }

    protected function response(): ResponseInterface
    {
        return new Response();
    }

    /** @return array<string, mixed> */
    protected function decode(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
