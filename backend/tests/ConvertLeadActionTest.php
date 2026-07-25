<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Actions\Admin\Leads\ConvertLeadAction;
use App\Application\Services\MagicLinkService;
use App\Application\Services\PracticeStepSeeder;
use App\Infrastructure\Logger;
use PDO;
use PDOStatement;
use Tests\Support\FakeMailer;

final class ConvertLeadActionTest extends TestCase
{
    private function action(PDO $pdo): ConvertLeadAction
    {
        return new ConvertLeadAction(
            $pdo,
            new MagicLinkService($pdo),
            new PracticeStepSeeder($pdo),
            new FakeMailer(),
            new Logger(sys_get_temp_dir() . '/rt-test.log'),
        );
    }

    public function testLeadNotFoundReturns404(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $response = ($this->action($pdo))(
            $this->request('POST', '/admin/leads/99/convert'),
            $this->response(),
            ['id' => '99']
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAlreadyConvertedReturns409(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id' => 5,
            'first_name' => 'Giulia',
            'last_name' => 'Sartori',
            'email' => 'giulia@example.it',
            'phone' => null,
            'request_type' => 'vendere',
            'converted_property_id' => 77, // già convertito
        ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $response = ($this->action($pdo))(
            $this->request('POST', '/admin/leads/5/convert'),
            $this->response(),
            ['id' => '5']
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('already_converted', $this->decode($response)['error']['code']);
    }

    public function testMissingOwnerEmailReturns422(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id' => 6,
            'first_name' => 'Luca',
            'last_name' => 'Favaro',
            'email' => null, // il lead non ha email
            'phone' => '+39 333 4445556',
            'request_type' => 'vendere',
            'converted_property_id' => null,
        ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        // Nessuna owner_email nel body → impossibile creare l'accesso
        $response = ($this->action($pdo))(
            $this->request('POST', '/admin/leads/6/convert'),
            $this->response(),
            ['id' => '6']
        );

        self::assertSame(422, $response->getStatusCode());
    }
}
