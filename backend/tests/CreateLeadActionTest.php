<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Actions\PublicSite\CreateLeadAction;
use App\Application\Security\ThrottleService;
use App\Infrastructure\Logger;
use PDO;
use PDOStatement;
use Tests\Support\FakeMailer;

final class CreateLeadActionTest extends TestCase
{
    private FakeMailer $mailer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailer = new FakeMailer();
    }

    private function logger(): Logger
    {
        return new Logger(sys_get_temp_dir() . '/rt-test.log');
    }

    public function testHoneypotDiscardsSilentlyWithoutTouchingDb(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');

        $action = new CreateLeadAction($pdo, $this->mailer, new ThrottleService($pdo), $this->logger());

        $response = $action(
            $this->request('POST', '/public/leads', [
                'first_name' => 'Bot',
                'last_name' => 'Spam',
                'email' => 'bot@spam.io',
                'website' => 'http://spam.io', // honeypot compilato
            ]),
            $this->response()
        );

        // Risposta identica al successo, ma nessun lead salvato e nessuna email
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($this->decode($response)['ok']);
        self::assertSame([], $this->mailer->sent);
    }

    public function testValidationErrors(): void
    {
        [$pdo, $stmt] = $this->pdoWithStatement();
        $stmt->method('fetchColumn')->willReturn(0); // throttle: sotto soglia

        $action = new CreateLeadAction($pdo, $this->mailer, new ThrottleService($pdo), $this->logger());

        $response = $action(
            $this->request('POST', '/public/leads', [
                'last_name' => 'Rossi',
                'email' => 'non-una-email',
            ]),
            $this->response()
        );

        self::assertSame(422, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame('validation', $body['error']['code']);
        self::assertArrayHasKey('first_name', $body['error']['fields']);
        self::assertArrayHasKey('email', $body['error']['fields']);
        self::assertSame([], $this->mailer->sent);
    }

    public function testMissingContactIsRejected(): void
    {
        [$pdo, $stmt] = $this->pdoWithStatement();
        $stmt->method('fetchColumn')->willReturn(0);

        $action = new CreateLeadAction($pdo, $this->mailer, new ThrottleService($pdo), $this->logger());

        $response = $action(
            $this->request('POST', '/public/leads', ['first_name' => 'Mario', 'last_name' => 'Rossi']),
            $this->response()
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('contact', $this->decode($response)['error']['fields']);
    }

    public function testValidLeadIsSavedAndAdminNotified(): void
    {
        [$pdo, $stmt] = $this->pdoWithStatement();
        // 1ª fetchColumn: conteggio throttle (0) — 2ª: RETURNING id del lead (123)
        $stmt->method('fetchColumn')->willReturnOnConsecutiveCalls(0, 123);

        $action = new CreateLeadAction($pdo, $this->mailer, new ThrottleService($pdo), $this->logger());

        $response = $action(
            $this->request('POST', '/public/leads', [
                'first_name' => 'Giulia',
                'last_name' => 'Sartori',
                'email' => 'giulia@example.it',
                'request_type' => 'vendere',
                'message' => 'Vorrei vendere il mio bilocale.',
            ]),
            $this->response()
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertCount(1, $this->mailer->sent);
        self::assertSame('nuovo_lead_admin', $this->mailer->sent[0]['template']);
        self::assertSame('Giulia Sartori', $this->mailer->sent[0]['vars']['lead_name']);
    }

    /** @return array{0: PDO&\PHPUnit\Framework\MockObject\MockObject, 1: PDOStatement&\PHPUnit\Framework\MockObject\MockObject} */
    private function pdoWithStatement(): array
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return [$pdo, $stmt];
    }
}
