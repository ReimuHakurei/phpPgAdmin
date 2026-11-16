<?php

declare(strict_types=1);

namespace Tests\Unit\DDD\ValueObjects\Server;

use PhpPgAdmin\DDD\ValueObjects\Server\Filename;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Filename::class)]
final class FilenameTest extends TestCase
{
    /**
     * Whether pg_dump exists there, Infrastructure\Shell\PgDump checks when it is needed. PHPUnit prints the output of
     * error_log(), so the expected empty output shows that nothing was logged.
     */
    public function testAMissingFileIsNotLogged(): void
    {
        $this->expectOutputString('');

        $filename = new Filename('/missing/pg_dump');

        self::assertSame('/missing/pg_dump', (string)$filename);
    }
}
