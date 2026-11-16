<?php

declare(strict_types=1);

namespace Tests\Unit\DDD\Entities;

use PhpPgAdmin\DDD\Entities\Server;
use PhpPgAdmin\DDD\Entities\ServerSession;
use PhpPgAdmin\DDD\ValueObjects\Server\DatabaseName;
use PhpPgAdmin\DDD\ValueObjects\Server\Filename;
use PhpPgAdmin\DDD\ValueObjects\Server\Host;
use PhpPgAdmin\DDD\ValueObjects\Server\Name;
use PhpPgAdmin\DDD\ValueObjects\Server\Port;
use PhpPgAdmin\DDD\ValueObjects\Server\SslMode;
use PhpPgAdmin\DDD\ValueObjects\ServerSession\Password;
use PhpPgAdmin\DDD\ValueObjects\ServerSession\Platform;
use PhpPgAdmin\DDD\ValueObjects\ServerSession\Username;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerSession::class)]
final class ServerSessionTest extends TestCase
{
    public function testFromServerTakesTheSettingsOfTheServer(): void
    {
        $server = new Server(
            name: new Name('Test Server'),
            host: new Host('db.example.org'),
            port: new Port(5_433),
            sslMode: SslMode::REQUIRE,
            defaultDb: new DatabaseName('postgres'),
            pgDumpPath: new Filename(__FILE__),
            pgDumpAllPath: new Filename(__DIR__),
        );

        $serverSession = ServerSession::fromServer(
            $server,
            new Username('alice'),
            new Password('secret'),
            new Platform('PostgreSQL 18'),
        );

        self::assertSame($server->id(), $serverSession->id());
        self::assertSame('Test Server', (string)$serverSession->Name);
        self::assertSame('postgres', (string)$serverSession->DefaultDb);
        self::assertSame(__FILE__, (string)$serverSession->PgDumpPath);
        self::assertSame(__DIR__, (string)$serverSession->PgDumpAllPath);
        self::assertSame('alice', (string)$serverSession->Username);
        self::assertSame('secret', (string)$serverSession->Password);
        self::assertSame('PostgreSQL 18', (string)$serverSession->Platform);
    }

    #[DataProvider('logins')]
    public function testLoginIsAllowed(
        string $username,
        string $password,
        bool $extraLoginSecurity,
        bool $expected,
    ): void {
        self::assertSame($expected, ServerSession::loginIsAllowed($username, $password, $extraLoginSecurity));
    }

    /**
     * @return iterable<string, array{string, string, bool, bool}>
     */
    public static function logins(): iterable
    {
        yield 'empty password without extra login security' => ['alice', '', false, true];
        yield 'postgres without extra login security' => ['postgres', '', false, true];
        yield 'password with extra login security' => ['alice', 'secret', true, true];
        yield 'empty password with extra login security' => ['alice', '', true, false];
        yield 'administrator with extra login security' => ['administrator', 'secret', true, false];
        yield 'pgsql with extra login security' => ['pgsql', 'secret', true, false];
        yield 'postgres with extra login security' => ['postgres', 'secret', true, false];
        yield 'root in uppercase with extra login security' => ['ROOT', 'secret', true, false];
    }
}
