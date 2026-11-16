<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Session;

use PhpPgAdmin\Infrastructure\Session\History;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The legacy Misc::saveScriptHistory() writes the history, keyed by the microtime of the query.
 */
#[CoversClass(History::class)]
final class HistoryTest extends TestCase
{
    private const SERVER_ID = '127.0.0.1:5432:allow';

    /**
     * @var array<string, array<string, array<string, float|string>>>
     */
    private array $history = [];

    public function testGetHistoryReturnsTheQueriesOfTheDatabase(): void
    {
        $this->saveQuery('postgres', '1731751200.1', 'SELECT 1', 'f');
        $this->saveQuery('postgres', '1731751200.2', 'SELECT 2', 't');
        $this->saveQuery('template1', '1731751200.3', 'SELECT 3', 'f');

        self::assertSame(
            [
                '1731751200.1' => ['paginate' => false, 'query' => 'SELECT 1'],
                '1731751200.2' => ['paginate' => true, 'query' => 'SELECT 2'],
            ],
            History::getHistory(self::SERVER_ID, 'postgres'),
        );
    }

    public function testGetHistorySkipsEntriesWithoutQuery(): void
    {
        $this->saveQuery('postgres', '1731751200.1', 'SELECT 1', 'f');
        $this->saveQuery('postgres', '1731751200.2', null, 'f');

        self::assertSame(
            ['1731751200.1' => ['paginate' => false, 'query' => 'SELECT 1']],
            History::getHistory(self::SERVER_ID, 'postgres'),
        );
    }

    public function testGetHistoryWithoutHistoryIsEmpty(): void
    {
        self::assertSame([], History::getHistory(self::SERVER_ID, 'postgres'));
    }

    public function testGetHistoryEntry(): void
    {
        $this->saveQuery('postgres', '1731751200.1', 'SELECT 1', 't');

        self::assertSame(
            ['paginate' => true, 'query' => 'SELECT 1'],
            History::getHistoryEntry(self::SERVER_ID, 'postgres', '1731751200.1'),
        );
        self::assertNull(History::getHistoryEntry(self::SERVER_ID, 'postgres', '1731751200.2'));
    }

    public function testClearRemovesOnlyTheHistoryOfTheDatabase(): void
    {
        $this->saveQuery('postgres', '1731751200.1', 'SELECT 1', 'f');
        $this->saveQuery('template1', '1731751200.2', 'SELECT 2', 'f');

        History::clear(self::SERVER_ID, 'postgres');

        self::assertSame([], History::getHistory(self::SERVER_ID, 'postgres'));
        self::assertCount(1, History::getHistory(self::SERVER_ID, 'template1'));
    }

    public function testDeleteEntryRemovesOnlyThatQuery(): void
    {
        $this->saveQuery('postgres', '1731751200.1', 'SELECT 1', 'f');
        $this->saveQuery('postgres', '1731751200.2', 'SELECT 2', 'f');

        History::deleteEntry(self::SERVER_ID, 'postgres', '1731751200.1');

        self::assertSame(
            ['1731751200.2' => ['paginate' => false, 'query' => 'SELECT 2']],
            History::getHistory(self::SERVER_ID, 'postgres'),
        );
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->history = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function saveQuery(string $database, string $queryId, ?string $query, string $paginate): void
    {
        $entry = [
            'paginate' => $paginate,
            'queryid' => (float)$queryId,
        ];

        if ($query !== null) {
            $entry['query'] = $query;
        }

        $this->history[$database][$queryId] = $entry;
        $_SESSION['history'] = [self::SERVER_ID => $this->history];
    }
}
