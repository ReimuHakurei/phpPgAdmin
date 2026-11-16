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

    public static function fromServer(Server $server, Username $username, Password $password, Platform $platform): self
    {
        return new self(
            username: $username,
            password: $password,
            name: $server->Name,
            host: $server->Host,
            port: $server->Port,
            sslMode: $server->SslMode,
            defaultDb: $server->DefaultDb,
            pgDumpPath: $server->PgDumpPath,
            pgDumpAllPath: $server->PgDumpAllPath,
            platform: $platform,
        );
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
