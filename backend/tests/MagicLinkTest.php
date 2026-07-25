<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Actions\Customer\RequestAccessAction;
use App\Application\Actions\Customer\VerifyAccessAction;
use App\Application\Security\JwtService;
use App\Application\Security\LoginRateLimiter;
use App\Application\Services\MagicLinkService;
use App\Infrastructure\Logger;
use PDO;
use PDOStatement;
use Tests\Support\FakeMailer;

final class MagicLinkTest extends TestCase
{
    private function logger(): Logger
    {
        return new Logger(sys_get_temp_dir() . '/rt-test.log');
    }

    public function testRequestAccessRespondsIdenticallyForUnknownEmail(): void
    {
        // Anti user-enumeration: email inesistente → stessa risposta generica 200
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false); // nessun owner
        $stmt->method('fetchColumn')->willReturn(0); // rate limiter sotto soglia

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $mailer = new FakeMailer();
        $action = new RequestAccessAction($pdo, new MagicLinkService($pdo), $mailer, new LoginRateLimiter($pdo), $this->logger());

        $response = $action(
            $this->request('POST', '/customer/request-access', ['email' => 'sconosciuto@example.it']),
            $this->response()
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Se l\'indirizzo è registrato', $this->decode($response)['message']);
        self::assertSame([], $mailer->sent, 'Nessuna email deve partire per indirizzi sconosciuti');
    }

    public function testRequestAccessSendsMagicLinkToExistingOwner(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['id' => 7, 'first_name' => 'Marco', 'email' => 'marco@example.it']);
        $stmt->method('fetchColumn')->willReturn(0);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $mailer = new FakeMailer();
        $action = new RequestAccessAction($pdo, new MagicLinkService($pdo), $mailer, new LoginRateLimiter($pdo), $this->logger());

        $response = $action(
            $this->request('POST', '/customer/request-access', ['email' => 'marco@example.it']),
            $this->response()
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $mailer->sent);
        self::assertSame('magic_link', $mailer->sent[0]['template']);
        self::assertStringContainsString('/app/access?token=', $mailer->sent[0]['vars']['link']);
        // Il token nel link è esadecimale a 64 caratteri
        self::assertMatchesRegularExpression('/token=[a-f0-9]{64}$/', $mailer->sent[0]['vars']['link']);
    }

    public function testVerifyRejectsMalformedToken(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $action = new VerifyAccessAction($pdo, new JwtService(), $this->logger());

        $response = $action(
            $this->request('POST', '/customer/verify', ['token' => 'abc']),
            $this->response()
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testVerifyRejectsUnknownOrUsedToken(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false); // hash non trovato / scaduto / usato

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $action = new VerifyAccessAction($pdo, new JwtService(), $this->logger());

        $response = $action(
            $this->request('POST', '/customer/verify', ['token' => str_repeat('a', 64)]),
            $this->response()
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testVerifyIssuesOwnerJwtForValidToken(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'link_id' => 3,
            'id' => 7,
            'agency_id' => 1,
            'role' => 'owner',
            'first_name' => 'Marco',
            'last_name' => 'Bortolin',
            'email' => 'marco@example.it',
            'is_active' => true,
        ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $jwt = new JwtService();
        $action = new VerifyAccessAction($pdo, $jwt, $this->logger());

        $response = $action(
            $this->request('POST', '/customer/verify', ['token' => str_repeat('b', 64)]),
            $this->response()
        );

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        $payload = $jwt->verify($body['token']);
        self::assertSame(7, $payload['uid']);
        self::assertSame('owner', $payload['role']);
        self::assertSame('Marco', $body['user']['first_name']);
    }
}
