<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Session;

use PhpPgAdmin\DDD\Entities\Server;
use PhpPgAdmin\DDD\ValueObjects\Server\Filename;
use PhpPgAdmin\DDD\ValueObjects\Server\Name;
use PhpPgAdmin\Infrastructure\Session\Logins;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Logins::class)]
final class LoginsTest extends TestCase
{
    private Server $server;

    public function testServerSessionAcceptsAnEmptyPassword(): void
    {
        $this->logIn('alice', '');

        $serverSession = Logins::serverSession($this->server->id(), [$this->server]);

        self::assertNotNull($serverSession);
        self::assertSame('alice', (string)$serverSession->Username);
        self::assertSame('', (string)$serverSession->Password);
    }

    public function testServerSessionKeepsThePasswordAsEntered(): void
    {
        $this->logIn('alice', ' secret ');

        $serverSession = Logins::serverSession($this->server->id(), [$this->server]);

        self::assertNotNull($serverSession);
        self::assertSame(' secret ', (string)$serverSession->Password);
    }

    public function testServerSessionTakesThePlatformOfTheLogin(): void
    {
        $this->logIn('alice', 'secret', 'PostgreSQL 18');

        $serverSession = Logins::serverSession($this->server->id(), [$this->server]);

        self::assertNotNull($serverSession);
        self::assertSame('PostgreSQL 18', (string)$serverSession->Platform);
    }

    public function testServerSessionWithoutPlatformHasAnUnknownPlatform(): void
    {
        $this->logIn('alice', 'secret');

        $serverSession = Logins::serverSession($this->server->id(), [$this->server]);

        self::assertNotNull($serverSession);
        self::assertSame('Unknown', (string)$serverSession->Platform);
    }

    /**
     * Only the login of the server counts, like in isLoggedIn(). The legacy pages kept credentials for all servers in
     * sharedUsername and sharedPassword, which no login sets any more.
     */
    public function testServerSessionIgnoresCredentialsForAllServers(): void
    {
        $this->logIn('alice', 'secret');
        $_SESSION['sharedUsername'] = 'bob';
        $_SESSION['sharedPassword'] = '';

        $serverSession = Logins::serverSession($this->server->id(), [$this->server]);

        self::assertNotNull($serverSession);
        self::assertSame('alice', (string)$serverSession->Username);
        self::assertSame('secret', (string)$serverSession->Password);
    }

    public function testServerSessionWithOnlyCredentialsForAllServersReturnsNull(): void
    {
        $_SESSION['sharedUsername'] = 'bob';
        $_SESSION['sharedPassword'] = 'secret';

        self::assertFalse(Logins::isLoggedIn($this->server->id()));
        self::assertNull(Logins::serverSession($this->server->id(), [$this->server]));
    }

    public function testServerSessionWithoutUsernameReturnsNull(): void
    {
        $this->logIn('', 'secret');

        self::assertNull(Logins::serverSession($this->server->id(), [$this->server]));
    }

    public function testServerSessionWithoutLoginReturnsNull(): void
    {
        self::assertNull(Logins::serverSession($this->server->id(), [$this->server]));
    }

    public function testServerSessionOfAnUnknownServerReturnsNull(): void
    {
        $this->logIn('alice', 'secret');

        self::assertNull(Logins::serverSession('db.example.org:5432:allow', [$this->server]));
    }

    public function testIsLoggedInWithAnEmptyPassword(): void
    {
        $this->logIn('alice', '');

        self::assertTrue(Logins::isLoggedIn($this->server->id()));
    }

    public function testIsLoggedInWithoutUsername(): void
    {
        $this->logIn('', 'secret');

        self::assertFalse(Logins::isLoggedIn($this->server->id()));
    }

    public function testIsLoggedInWithoutLogin(): void
    {
        self::assertFalse(Logins::isLoggedIn($this->server->id()));
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

    private function logIn(string $username, string $password, ?string $platform = null): void
    {
        $login = [
            'password' => $password,
            'username' => $username,
        ];

        if ($platform !== null) {
            $login['platform'] = $platform;
        }

        $_SESSION['webdbLogin'] = [$this->server->id() => $login];
    }
}
