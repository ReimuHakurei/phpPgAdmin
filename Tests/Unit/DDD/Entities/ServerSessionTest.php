<?php

declare(strict_types=1);

namespace Tests\Unit\DDD\Entities;

use PhpPgAdmin\DDD\Entities\Server;
use PhpPgAdmin\DDD\Entities\ServerSession;
use PhpPgAdmin\DDD\ValueObjects\Server\Filename;
use PhpPgAdmin\DDD\ValueObjects\Server\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerSession::class)]
final class ServerSessionTest extends TestCase
{
    private Server $server;

    public function testFromServerIdAcceptsAnEmptyPassword(): void
    {
        $this->logIn('alice', '');

        $serverSession = ServerSession::fromServerId($this->server->id(), [$this->server]);

        self::assertNotNull($serverSession);
        self::assertSame('alice', (string)$serverSession->Username);
        self::assertSame('', (string)$serverSession->Password);
    }

    public function testFromServerIdKeepsThePasswordAsEntered(): void
    {
        $this->logIn('alice', ' secret ');

        $serverSession = ServerSession::fromServerId($this->server->id(), [$this->server]);

        self::assertNotNull($serverSession);
        self::assertSame(' secret ', (string)$serverSession->Password);
    }

    /**
     * Only the login of the server counts, like in isLoggedIn(). The legacy pages kept credentials for all servers in
     * sharedUsername and sharedPassword, which no login sets any more.
     */
    public function testFromServerIdIgnoresCredentialsForAllServers(): void
    {
        $this->logIn('alice', 'secret');
        $_SESSION['sharedUsername'] = 'bob';
        $_SESSION['sharedPassword'] = '';

        $serverSession = ServerSession::fromServerId($this->server->id(), [$this->server]);

        self::assertNotNull($serverSession);
        self::assertSame('alice', (string)$serverSession->Username);
        self::assertSame('secret', (string)$serverSession->Password);
    }

    public function testFromServerIdWithOnlyCredentialsForAllServersReturnsNull(): void
    {
        $_SESSION['sharedUsername'] = 'bob';
        $_SESSION['sharedPassword'] = 'secret';

        self::assertFalse(ServerSession::isLoggedIn($this->server->id()));
        self::assertNull(ServerSession::fromServerId($this->server->id(), [$this->server]));
    }

    public function testFromServerIdWithoutUsernameReturnsNull(): void
    {
        $this->logIn('', 'secret');

        self::assertNull(ServerSession::fromServerId($this->server->id(), [$this->server]));
    }

    public function testFromServerIdWithoutLoginReturnsNull(): void
    {
        self::assertNull(ServerSession::fromServerId($this->server->id(), [$this->server]));
    }

    public function testIsLoggedInWithAnEmptyPassword(): void
    {
        $this->logIn('alice', '');

        self::assertTrue(ServerSession::isLoggedIn($this->server->id()));
    }

    public function testIsLoggedInWithoutUsername(): void
    {
        $this->logIn('', 'secret');

        self::assertFalse(ServerSession::isLoggedIn($this->server->id()));
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

    protected function setUp(): void
    {
        $_SESSION = [];

        // An existing file as pg_dump path, so Filename does not log a missing file.
        $this->server = new Server(
            name: new Name('Test Server'),
            pgDumpPath: new Filename(__FILE__),
            pgDumpAllPath: new Filename(__FILE__),
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function logIn(string $username, string $password): void
    {
        $_SESSION['webdbLogin'] = [
            $this->server->id() => [
                'password' => $password,
                'username' => $username,
            ],
        ];
    }
}
