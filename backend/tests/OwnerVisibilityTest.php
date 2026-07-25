<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Actions\Customer\CustomerFeedAction;
use App\Application\Actions\Customer\GetMyPropertyAction;
use PDO;
use PDOStatement;

/**
 * Test di sicurezza esplicito: un owner NON può vedere immobili altrui.
 * L'enforcement è a livello di query (owner_user_id = uid dal token),
 * qui verifichiamo che l'uid del token sia esattamente ciò che viene
 * usato nel binding e che senza match la risposta sia 404.
 */
final class OwnerVisibilityTest extends TestCase
{
    public function testPropertyLookupIsScopedToTokenUid(): void
    {
        $capturedParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(function (array $params) use (&$capturedParams): bool {
            $capturedParams[] = $params;
            return true;
        });
        $stmt->method('fetch')->willReturn(false); // nessun immobile per questo uid

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($stmt) {
            // Ogni query sull'immobile del cliente DEVE filtrare per owner_user_id
            self::assertStringContainsString('owner_user_id', $sql);
            return $stmt;
        });

        $action = new GetMyPropertyAction($pdo);
        $request = $this->request('GET', '/customer/property')->withAttribute('auth_uid', 42);

        $response = $action($request, $this->response());

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('no_property', $this->decode($response)['error']['code']);
        self::assertSame([['uid' => 42]], $capturedParams, 'La query deve usare esattamente l\'uid del token');
    }

    public function testFeedEndpointsReturn404ForOwnerWithoutProperty(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $action = new CustomerFeedAction($pdo);
        $request = $this->request('GET', '/customer/visits')->withAttribute('auth_uid', 999);

        foreach (['visits', 'proposals', 'marketing', 'practiceSteps', 'timeline'] as $method) {
            $response = $action->{$method}($request, $this->response());
            self::assertSame(404, $response->getStatusCode(), "Metodo {$method} deve negare l'accesso senza immobile proprio");
        }
    }

    public function testVisibleToOwnerFilterIsInEveryFeedQuery(): void
    {
        $queries = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        // propertyIdForOwner trova l'immobile 10; le fetch successive non contano
        $stmt->method('fetch')->willReturn(['id' => 10]);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$queries, $stmt) {
            $queries[] = $sql;
            return $stmt;
        });

        $action = new CustomerFeedAction($pdo);
        $request = $this->request('GET', '/customer/visits')->withAttribute('auth_uid', 42);

        foreach (['visits', 'proposals', 'marketing', 'practiceSteps'] as $method) {
            $queries = [];
            $action->{$method}($request, $this->response());
            // La seconda query (dopo il lookup immobile) deve filtrare visible_to_owner
            self::assertStringContainsString('visible_to_owner = true', $queries[1] ?? '', "Query di {$method} senza filtro visibilità");
        }
    }
}
