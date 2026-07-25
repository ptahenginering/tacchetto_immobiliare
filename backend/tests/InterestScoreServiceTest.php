<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Services\InterestScoreService;
use PDO;
use PDOStatement;

final class InterestScoreServiceTest extends TestCase
{
    public function testScoreFormulaAndTrendUp(): void
    {
        // Attuale: 3 visite, 2 feedback positivi, 1 proposta attiva, 2 appuntamenti futuri
        //   3*6 + 2*8 + 1*25 + 2*5 = 18+16+25+10 = 69
        // Precedente: tutto zero → trend up
        $service = $this->serviceWithWindows(
            ['visits' => 3, 'positive_feedback' => 2, 'active_proposals' => 1, 'future_appointments' => 2],
            ['visits' => 0, 'positive_feedback' => 0, 'active_proposals' => 0, 'future_appointments' => 0],
        );

        $result = $service->forProperty(1);

        self::assertSame(69, $result['score']);
        self::assertSame('up', $result['trend']);
        self::assertSame(3, $result['components']['visits']);
    }

    public function testScoreIsCappedAt100(): void
    {
        $service = $this->serviceWithWindows(
            ['visits' => 20, 'positive_feedback' => 10, 'active_proposals' => 4, 'future_appointments' => 10],
            ['visits' => 0, 'positive_feedback' => 0, 'active_proposals' => 0, 'future_appointments' => 0],
        );

        self::assertSame(100, $service->forProperty(1)['score']);
    }

    public function testTrendDown(): void
    {
        $service = $this->serviceWithWindows(
            ['visits' => 1, 'positive_feedback' => 0, 'active_proposals' => 0, 'future_appointments' => 0],
            ['visits' => 5, 'positive_feedback' => 3, 'active_proposals' => 0, 'future_appointments' => 1],
        );

        self::assertSame('down', $service->forProperty(1)['trend']);
    }

    public function testTrendFlatWhenEqual(): void
    {
        $window = ['visits' => 2, 'positive_feedback' => 1, 'active_proposals' => 0, 'future_appointments' => 1];
        $service = $this->serviceWithWindows($window, $window);

        self::assertSame('flat', $service->forProperty(1)['trend']);
    }

    /**
     * @param array<string,int> $current
     * @param array<string,int> $previous
     */
    private function serviceWithWindows(array $current, array $previous): InterestScoreService
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturnOnConsecutiveCalls($current, $previous);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return new InterestScoreService($pdo);
    }
}
