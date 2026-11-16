<?php

declare(strict_types=1);

namespace PhpPgAdmin\Infrastructure\Shell;

use PhpPgAdmin\DDD\Entities\Server;
use PhpPgAdmin\DDD\ValueObjects\Server\Filename;

/**
 * The programs pg_dump and pg_dumpall at the paths that the configuration of a server gives.
 */
final class PgDump
{
    /**
     * The path of pg_dump, or with $all of pg_dumpall, if the program exists there and prints its version.
     */
    public static function tryGetFilename(Server $server, bool $all): ?Filename
    {
        $pgDumpFilename = $all
            ? $server->PgDumpAllPath
            : $server->PgDumpPath;

        if (!file_exists((string)$pgDumpFilename)) {
            return null;
        }

        $pregMatchResult = preg_match(
            "/(\d+(?:\.\d+)?)(?:\.\d+)?.*$/",
            exec($pgDumpFilename . " --version") ?: '',
        );

        if (!$pregMatchResult) {
            return null;
        }

        return $pgDumpFilename;
    }
}
