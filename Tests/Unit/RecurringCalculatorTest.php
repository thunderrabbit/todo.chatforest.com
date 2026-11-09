<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * Next-occurrence dates keep the time of day from the todo's create date.
 * 09-nov-2026 is a Monday.
 */
class RecurringCalculatorTest extends Unit
{
    private const COMPLETED = '10:00:00 09-nov-2026';
    private const CREATED = '08:30:00 01-nov-2026';

    private \Todo\RecurringCalculator $calc;

    protected function _before(): void
    {
        $this->calc = new \Todo\RecurringCalculator();
    }

    public function testDailyIsTheNextDay(): void
    {
        $this->assertSame(
            '08:30:00 10-nov-2026',
            $this->calc->calculateNextOccurrence('#d', self::COMPLETED, self::CREATED)
        );
    }

    public function testWeeklySkipsToTheNextListedWeekday(): void
    {
        // Completed on a Monday: the next listed day is Friday, not today.
        $this->assertSame(
            '08:30:00 13-nov-2026',
            $this->calc->calculateNextOccurrence('#w:mon,fri', self::COMPLETED, self::CREATED)
        );
    }

    public function testMonthlyTakesTheNextListedDayThisMonth(): void
    {
        $this->assertSame(
            '08:30:00 15-nov-2026',
            $this->calc->calculateNextOccurrence('#m:1,15', self::COMPLETED, self::CREATED)
        );
    }

    public function testUnknownMarkerThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->calc->calculateNextOccurrence('#y', self::COMPLETED, self::CREATED);
    }
}
