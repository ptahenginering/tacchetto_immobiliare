<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Security\JwtService;
use RuntimeException;

final class JwtServiceTest extends TestCase
{
    public function testIssueAndVerifyRoundtrip(): void
    {
        $svc = new JwtService();
        $token = $svc->issue(['uid' => 42, 'role' => 'admin', 'agency_id' => 1]);

        $payload = $svc->verify($token);

        self::assertSame(42, $payload['uid']);
        self::assertSame('admin', $payload['role']);
        self::assertSame(1, $payload['agency_id']);
        self::assertSame('rt-casa-live', $payload['iss']);
    }

    public function testFailFastWithoutSecret(): void
    {
        $this->expectException(RuntimeException::class);
        new JwtService('');
    }

    public function testFailFastWithShortSecret(): void
    {
        $this->expectException(RuntimeException::class);
        new JwtService('corto');
    }

    public function testVerifyRejectsGarbage(): void
    {
        $svc = new JwtService();
        $this->expectException(\Throwable::class);
        $svc->verify('token.non.valido');
    }

    public function testVerifyRejectsTokenSignedWithOtherSecret(): void
    {
        $other = new JwtService(str_repeat('x', 64));
        $token = $other->issue(['uid' => 1, 'role' => 'owner', 'agency_id' => 1]);

        $svc = new JwtService();
        $this->expectException(\Throwable::class);
        $svc->verify($token);
    }

    public function testTtlPerRole(): void
    {
        self::assertSame(2592000, JwtService::ttlForRole('owner'));
        self::assertSame(28800, JwtService::ttlForRole('admin'));
        self::assertSame(28800, JwtService::ttlForRole('agent'));
    }

    public function testExpiredTokenIsRejected(): void
    {
        $svc = new JwtService();
        $token = $svc->issue(['uid' => 1, 'role' => 'admin', 'agency_id' => 1], -10);

        $this->expectException(\Throwable::class);
        $svc->verify($token);
    }
}
