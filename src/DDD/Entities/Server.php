<?php

declare(strict_types=1);

namespace PhpPgAdmin\DDD\Entities;

use PhpPgAdmin\DDD\ValueObjects\Server\{DatabaseName, Filename, Host, Name, Port, SslMode};

/**
 * @property-read Name $Name
 * @property-read Host $Host
 * @property-read Port $Port
 * @property-read SslMode $SslMode
 * @property-read DatabaseName $DefaultDb
 * @property-read Filename $PgDumpPath
 * @property-read Filename $PgDumpAllPath
 */
class Server
{
    public function __construct(
        protected Name $name,
        protected Host $host = new Host(),
        protected Port $port = new Port(),
        protected SslMode $sslMode = SslMode::ALLOW,
        protected DatabaseName $defaultDb = new DatabaseName('template1'),
        protected Filename $pgDumpPath = new Filename('/usr/bin/pg_dump'),
        protected Filename $pgDumpAllPath = new Filename('/usr/bin/pg_dumpall'),
    ) {
    }

    /**
     * @param array<mixed> $input
     */
    public static function fromArray(array $input): self
    {
        $name = '';
        $host = '127.0.0.1';
        $port = 5_432;
        $sslMode = 'allow';
        $defaultDb = 'template1';
        $pgDumpPath = '/usr/bin/pg_dump';
        $pgDumpAllPath = '/usr/bin/pg_dumpall';

        if (isset($input['desc']) && is_string($input['desc'])) {
            $name = $input['desc'];
        }

        if (isset($input['host']) && is_string($input['host'])) {
            $host = $input['host'];
        }

        if (isset($input['port']) && is_int($input['port'])) {
            $port = $input['port'];
        }

        if (isset($input['sslmode']) && is_string($input['sslmode'])) {
            $sslMode = strtolower($input['sslmode']);
        }

        if (isset($input['defaultdb']) && is_string($input['defaultdb'])) {
            $defaultDb = $input['defaultdb'];
        }

        if (isset($input['pgdumppath']) && is_string($input['pgdumppath'])) {
            $pgDumpPath = $input['pgdumppath'];
        }

        if (isset($input['pg_dumpall_path']) && is_string($input['pg_dumpall_path'])) {
            $pgDumpAllPath = $input['pg_dumpall_path'];
        }

        return new self(
            name: new Name($name),
            host: new Host($host),
            port: new Port($port),
            sslMode: SslMode::from($sslMode),
            defaultDb: new DatabaseName($defaultDb),
            pgDumpPath: new Filename($pgDumpPath),
            pgDumpAllPath: new Filename($pgDumpAllPath),
        );
    }

    public function id(): string
    {
        return (string)$this->host . ':' . $this->port->Value . ':' . $this->sslMode->value;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'Name' => $this->name,
            'Host' => $this->host,
            'Port' => $this->port,
            'SslMode' => $this->sslMode,
            'DefaultDb' => $this->defaultDb,
            'PgDumpPath' => $this->pgDumpPath,
            'PgDumpAllPath' => $this->pgDumpAllPath,
            default => null,
        };
    }
}
