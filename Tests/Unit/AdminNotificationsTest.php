<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * Admin\Notifications over an in-memory SQLite (no server, gone with the
 * process).
 */
class AdminNotificationsTest extends Unit
{
    private \PDO $pdo;

    protected function _before(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    private function createTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE notifications (
                notification_id INTEGER PRIMARY KEY AUTOINCREMENT,
                message TEXT NOT NULL,
                link TEXT,
                show_from TEXT NOT NULL,
                completed_at TEXT,
                completed_by INTEGER,
                created_at TEXT NOT NULL
            )"
        );
    }

    private function insert(string $show_from, ?string $completed_at = null): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications (message, show_from, completed_at, created_at)
             VALUES ('m', ?, ?, '2026-11-16 00:00:00')"
        );
        $stmt->execute([$show_from, $completed_at]);
    }

    private function notifications(): \Admin\Notifications
    {
        $now = new \DateTimeImmutable('2027-04-01 00:00:00', new \DateTimeZone('UTC'));
        return new \Admin\Notifications($this->pdo, $now);
    }

    public function testCountsDueUncompletedRows(): void
    {
        $this->createTable();
        $this->insert('2027-03-31 23:59:59');
        $this->insert('2027-04-01 00:00:00');
        $this->insert('2027-04-01 00:00:01');
        $this->insert('2027-03-01 00:00:00', '2027-03-02 00:00:00');

        $this->assertSame(2, $this->notifications()->dueCount());
    }

    public function testMissingTableCountsZero(): void
    {
        $this->assertSame(0, $this->notifications()->dueCount());
    }

    public function testNowInAnotherZoneIsReadAsUtc(): void
    {
        $this->createTable();
        $this->insert('2027-03-31 20:00:00');
        // 2027-04-01 05:00 in +09:00 is 2027-03-31 20:00 UTC: due, to the second.
        $now = new \DateTimeImmutable('2027-04-01 05:00:00', new \DateTimeZone('+09:00'));

        $this->assertSame(1, (new \Admin\Notifications($this->pdo, $now))->dueCount());
    }

    public function testListDueMatchesDueCount(): void
    {
        $this->createTable();
        $this->insert('2027-04-01 00:00:00');
        $this->insert('2027-03-31 23:59:59');
        $this->insert('2027-04-01 00:00:01');
        $this->insert('2027-03-01 00:00:00', '2027-03-02 00:00:00');

        $due = $this->notifications()->listDue();

        $this->assertSame(
            ['2027-03-31 23:59:59', '2027-04-01 00:00:00'],
            array_map(static fn(\Admin\Notification $n): string => $n->show_from, $due),
        );
        $this->assertSame(2, $due[0]->notification_id);
        $this->assertSame('m', $due[0]->message);
        $this->assertNull($due[0]->link);
    }

    public function testListDueCarriesTheLink(): void
    {
        $this->createTable();
        $this->pdo->exec(
            "INSERT INTO notifications (message, link, show_from, created_at)
             VALUES ('Revisit', '/tqiq/', '2027-04-01 00:00:00', '2026-11-16 00:00:00')"
        );

        $this->assertSame('/tqiq/', $this->notifications()->listDue()[0]->link);
    }

    public function testMissingTableListsNothing(): void
    {
        $this->assertSame([], $this->notifications()->listDue());
    }

    public function testListUpcomingIsWhatIsNeitherDueNorCompleted(): void
    {
        $this->createTable();
        $this->insert('2027-06-01 00:00:00');
        $this->insert('2027-04-01 00:00:00');
        $this->insert('2027-04-01 00:00:01');
        $this->insert('2027-05-01 00:00:00', '2027-03-02 00:00:00');

        $this->assertSame(
            ['2027-04-01 00:00:01', '2027-06-01 00:00:00'],
            array_map(
                static fn(\Admin\Notification $n): string => $n->show_from,
                $this->notifications()->listUpcoming(),
            ),
        );
    }

    public function testMissingTableListsNothingUpcoming(): void
    {
        $this->assertSame([], $this->notifications()->listUpcoming());
    }
}
