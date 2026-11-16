<?php

declare(strict_types=1);

namespace Tests\Unit\Website;

use PhpPgAdmin\Application\Exceptions\FileNotFoundException;
use PhpPgAdmin\DDD\Entities\Server;
use PhpPgAdmin\DDD\ValueObjects\Server\{Filename, Name};
use PhpPgAdmin\Website\DbExport;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversMethod(DbExport::class, 'ensurePgDumpFilenameExists')]
final class DbExportTest extends TestCase
{
    public function testTheErrorNamesTheConfiguredPathOfPgDump(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage(
            'Export error: Failed to execute pg_dump (given path in your conf/config.inc.php : /missing/pg_dump).'
                . ' Please, fix this path in your configuration and relog.',
        );

        DbExport::ensurePgDumpFilenameExists(self::serverWithoutPgDump(), false);
    }

    public function testTheErrorNamesTheConfiguredPathOfPgDumpAll(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage(
            'Export error: Failed to execute pg_dumpall (given path in your conf/config.inc.php : /missing/pg_dumpall).'
                . ' Please, fix this path in your configuration and relog.',
        );

        DbExport::ensurePgDumpFilenameExists(self::serverWithoutPgDump(), true);
    }

    private static function serverWithoutPgDump(): Server
    {
        return new Server(
            name: new Name('Test Server'),
            pgDumpPath: new Filename('/missing/pg_dump'),
            pgDumpAllPath: new Filename('/missing/pg_dumpall'),
        );
    }
}
