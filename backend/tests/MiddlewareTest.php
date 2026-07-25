<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Middleware\AdminMiddleware;
use App\Application\Middleware\CustomerMiddleware;
use App\Application\Security\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class MiddlewareTest extends TestCase
{
    private JwtService $jwt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = new JwtService();
    }

    private function handler(?ServerRequestInterface &$captured = null): RequestHandlerInterface
    {
        return new class($captured) implements RequestHandlerInterface {
            public function __construct(private mixed &$captured)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request;
                $response = new Response(200);
                $response->getBody()->write('{"ok":true}');
                return $response;
            }
        };
    }

    public function testMissingTokenReturns401(): void
    {
        $middleware = new AdminMiddleware($this->jwt);
        $response = $middleware->process($this->request('GET', '/admin/leads'), $this->handler());

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('unauthorized', $this->decode($response)['error']['code']);
    }

    public function testInvalidTokenReturns401(): void
    {
        $middleware = new AdminMiddleware($this->jwt);
        $request = $this->request('GET', '/admin/leads', [], ['Authorization' => 'Bearer non-valido']);
        $response = $middleware->process($request, $this->handler());

        self::assertSame(401, $response->getStatusCode());
    }

    public function testOwnerTokenOnAdminRoutesReturns403(): void
    {
        $token = $this->jwt->issue(['uid' => 7, 'role' => 'owner', 'agency_id' => 1]);
        $middleware = new AdminMiddleware($this->jwt);
        $request = $this->request('GET', '/admin/leads', [], ['Authorization' => "Bearer {$token}"]);
        $response = $middleware->process($request, $this->handler());

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('forbidden', $this->decode($response)['error']['code']);
    }

    public function testAdminTokenOnCustomerRoutesReturns403(): void
    {
        $token = $this->jwt->issue(['uid' => 1, 'role' => 'admin', 'agency_id' => 1]);
        $middleware = new CustomerMiddleware($this->jwt);
        $request = $this->request('GET', '/customer/property', [], ['Authorization' => "Bearer {$token}"]);
        $response = $middleware->process($request, $this->handler());

        self::assertSame(403, $response->getStatusCode());
    }

    public function testValidTokenPassesAndAttachesAttributes(): void
    {
        $token = $this->jwt->issue(['uid' => 42, 'role' => 'agent', 'agency_id' => 1]);
        $middleware = new AdminMiddleware($this->jwt);
        $request = $this->request('GET', '/admin/leads', [], ['Authorization' => "Bearer {$token}"]);

        $captured = null;
        $response = $middleware->process($request, $this->handler($captured));

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($captured);
        self::assertSame(42, $captured->getAttribute('auth_uid'));
        self::assertSame('agent', $captured->getAttribute('auth_role'));
        self::assertSame(1, $captured->getAttribute('auth_agency_id'));
    }
}
