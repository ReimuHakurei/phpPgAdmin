<?php

declare(strict_types=1);

namespace PhpPgAdmin\DDD\Entities;

use PhpPgAdmin\DDD\ValueObjects\Server\{DatabaseName, Filename, Host, Name, Port, SslMode};
use PhpPgAdmin\DDD\ValueObjects\ServerSession\{Username, Password, Platform};

/**
 * @property-read DatabaseName $DefaultDb
 * @property-read Host $Host
 * @property-read Name $Name
 * @property-read Password $Password
 * @property-read Filename $PgDumpAllPath
 * @property-read Filename $PgDumpPath
 * @property-read Platform $Platform
 * @property-read Port $Port
 * @property-read SslMode $SslMode
 * @property-read Username $Username
 */
final class ServerSession extends Server
{
    /**
     * Refused with extra login security, compared in lowercase (as in the legacy Misc::getDatabaseAccessor()).
     */
    private const EXTRA_LOGIN_SECURITY_DISALLOWED_USERNAMES = ['administrator', 'pgsql', 'postgres', 'root'];

    public function __construct(
        private Username $username,
        private Password $password,
        Name $name,
        Host $host = new Host(),
        Port $port = new Port(),
        SslMode $sslMode = SslMode::ALLOW,
        DatabaseName $defaultDb = new DatabaseName('template1'),
        Filename $pgDumpPath = new Filename('/usr/bin/pg_dump'),
        Filename $pgDumpAllPath = new Filename('/usr/bin/pg_dumpall'),
        private Platform $platform = new Platform('PostgreSQL'),
    ) {
        parent::__construct(
            name: $name,
            host: $host,
            port: $port,
            sslMode: $sslMode,
            defaultDb: $defaultDb,
            pgDumpPath: $pgDumpPath,
            pgDumpAllPath: $pgDumpAllPath,
        );
    }

    /**
     * @param array<Server> $servers
     */
    public static function fromServerId(string $serverId, array $servers): ?self
    {
        foreach ($servers as $server) {
            if ($server->id() !== $serverId) {
                continue;
            }

            $username = '';
            $password = '';
            $platform = 'Unknown';

            if (
                isset($_SESSION['webdbLogin']) &&
                is_array($_SESSION['webdbLogin']) &&
                isset($_SESSION['webdbLogin'][$serverId]) &&
                is_array($_SESSION['webdbLogin'][$serverId])
            ) {
                if (
                    isset($_SESSION['webdbLogin'][$serverId]['username']) &&
                    is_string($_SESSION['webdbLogin'][$serverId]['username']) &&
                    isset($_SESSION['webdbLogin'][$serverId]['password']) &&
                    is_string($_SESSION['webdbLogin'][$serverId]['password'])
                ) {
                    $username = $_SESSION['webdbLogin'][$serverId]['username'];
                    $password = $_SESSION['webdbLogin'][$serverId]['password'];
                }

                if (
                    isset($_SESSION['webdbLogin'][$serverId]['platform']) &&
                    is_string($_SESSION['webdbLogin'][$serverId]['platform'])
                ) {
                    $platform = $_SESSION['webdbLogin'][$serverId]['platform'];
                }
            }

            // The password may be empty, e.g. with trust authentication; loginIsAllowed() decided that at login.
            if ($username !== '') {
                return new self(
                    username: new Username($username),
                    password: new Password($password),
                    name: new Name((string)$server->Name),
                    host: new Host((string)$server->Host),
                    port: new Port($server->Port->Value),
                    sslMode: $server->SslMode,
                    defaultDb: new DatabaseName((string)$server->DefaultDb),
                    pgDumpPath: new Filename((string)$server->PgDumpPath),
                    pgDumpAllPath: new Filename((string)$server->PgDumpAllPath),
                    platform: new Platform($platform),
                );
            }
        }

        return null;
    }

    public static function isLoggedIn(string $serverId): bool
    {
        if (!isset($_SESSION['webdbLogin']) || !is_array($_SESSION['webdbLogin'])) {
            return false;
        }

        if (!isset($_SESSION['webdbLogin'][$serverId]) || !is_array($_SESSION['webdbLogin'][$serverId])) {
            return false;
        }

        if (
            !isset($_SESSION['webdbLogin'][$serverId]['username']) ||
            !isset($_SESSION['webdbLogin'][$serverId]['password'])
        ) {
            return false;
        }

        $sessionUsername = $_SESSION['webdbLogin'][$serverId]['username'];
        $sessionPassword = $_SESSION['webdbLogin'][$serverId]['password'];

        if (!is_string($sessionUsername) || !is_string($sessionPassword)) {
            return false;
        }

        return $sessionUsername !== '';
    }

    /**
     * With extra login security (extra_login_security in conf/config.yml-dist), logins without a password or with
     * one of the well-known superuser names are refused.
     */
    public static function loginIsAllowed(string $username, string $password, bool $extraLoginSecurity): bool
    {
        if (!$extraLoginSecurity) {
            return true;
        }

        return $password !== ''
            && !in_array(strtolower($username), self::EXTRA_LOGIN_SECURITY_DISALLOWED_USERNAMES, true);
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'Password' => $this->password,
            'Platform' => $this->platform,
            'Username' => $this->username,
            default => parent::__get($name),
        };
    }
}
