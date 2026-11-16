<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Shell;

use PhpPgAdmin\DDD\Entities\Server;
use PhpPgAdmin\DDD\ValueObjects\Server\Filename;
use PhpPgAdmin\DDD\ValueObjects\Server\Name;
use PhpPgAdmin\Infrastructure\Shell\PgDump;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Shell scripts stand in for pg_dump and pg_dumpall, so the tests do not depend on an installed PostgreSQL.
 */
#[CoversClass(PgDump::class)]
final class PgDumpTest extends TestCase
{
    private string $pgDump;

    private string $pgDumpAll;

    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    public function testTryGetFilenameReturnsThePgDumpPathOfTheServer(): void
    {
        $server = $this->server();

        self::assertSame($server->PgDumpPath, PgDump::tryGetFilename($server, false));
    }

    public function testTryGetFilenameWithAllReturnsThePgDumpAllPathOfTheServer(): void
    {
        $server = $this->server();

        self::assertSame($server->PgDumpAllPath, PgDump::tryGetFilename($server, true));
    }

    public function testTryGetFilenameOfAPathWithSpaces(): void
    {
        $this->pgDump = $this->tempFile('pg dump');
        $this->writeProgram($this->pgDump, 'pg_dump (PostgreSQL) 18.1');
        $server = $this->server();

        self::assertSame($server->PgDumpPath, PgDump::tryGetFilename($server, false));
    }

    public function testTryGetFilenameOfAMissingProgramReturnsNull(): void
    {
        $server = $this->server();
        unlink($this->pgDump);

        self::assertNull(PgDump::tryGetFilename($server, false));
    }

    public function testTryGetFilenameOfAProgramWithoutVersionReturnsNull(): void
    {
        $this->writeProgram($this->pgDump, 'pg_dump');

        self::assertNull(PgDump::tryGetFilename($this->server(), false));
    }

    protected function setUp(): void
    {
        $this->pgDump = $this->tempFile();
        $this->pgDumpAll = $this->tempFile();

        $this->writeProgram($this->pgDump, 'pg_dump (PostgreSQL) 18.1');
        $this->writeProgram($this->pgDumpAll, 'pg_dumpall (PostgreSQL) 18.1');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function server(): Server
    {
        return new Server(
            name: new Name('Test Server'),
            pgDumpPath: new Filename($this->pgDump),
            pgDumpAllPath: new Filename($this->pgDumpAll),
        );
    }

    private function tempFile(string $prefix = 'pg_dump'): string
    {
        $filename = tempnam(sys_get_temp_dir(), $prefix);
        self::assertIsString($filename);
        $this->tempFiles[] = $filename;

        return $filename;
    }

    private function writeProgram(string $filename, string $output): void
    {
        file_put_contents($filename, "#!/bin/sh\necho '{$output}'\n");
        chmod($filename, 0o700);
    }
}
