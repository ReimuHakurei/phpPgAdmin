<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\Application\Exceptions\{FileNotFoundException, ServerNotFoundException, ServerSessionNotFoundException};
use PhpPgAdmin\Config;
use PhpPgAdmin\DDD\Entities\Server;
use PhpPgAdmin\DDD\ValueObjects\Server\Filename;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;
use PhpPgAdmin\Infrastructure\Shell\PgDump;
use PhpPgAdmin\Website;

final class DbExport extends Website
{
    public function __construct()
    {
        parent::__construct();

        if (!ini_get('safe_mode')) {
            set_time_limit(0);
        }

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest();
        }
    }

    /**
     * The path of pg_dump, or with $all of pg_dumpall, if the program runs there.
     *
     * @throws FileNotFoundException if it does not.
     */
    public static function ensurePgDumpFilenameExists(Server $server, bool $all): Filename
    {
        $dumpFilename = PgDump::tryGetFilename($server, $all);

        if (is_null($dumpFilename)) {
            $errorMessageFormat = _(
                'Export error: Failed to execute pg_dump (given path in your conf/config.yml : %s).'
                . ' Please, fix this path in your configuration.',
            );

            if ($all) {
                $errorMessageFormat = _(
                    'Export error: Failed to execute pg_dumpall (given path in your conf/config.yml : %s).'
                    . ' Please, fix this path in your configuration.',
                );
            }

            $configuredFilename = $all
                ? $server->PgDumpAllPath
                : $server->PgDumpPath;

            throw new FileNotFoundException(sprintf($errorMessageFormat, (string)$configuredFilename));
        }

        return $dumpFilename;
    }

    private function applyCompressSpecificCommandArguments(string $command, bool $dumpAll): string
    {
        $output = RequestParameter::getString('output') ?? '';

        if ($output === 'gzipped' && !$dumpAll) {
            $command .= " -Z 9";
        }

        return $command;
    }

    private function applySubjectSpecificCommandArguments(string $command, string $subject): string
    {
        $schema = RequestParameter::getString('schema') ?? '';

        if ($subject === 'schema') {
            $command .= " -n " . escapeshellarg("\"{$schema}\"");
        } elseif ($subject === 'table' || $subject === 'view') {
            $object = RequestParameter::getString($subject);
            $command .= " -t " . escapeshellarg("\"{$schema}\".\"{$object}\"");
        }

        return $command;
    }

    private function applyWhatSpecificCommandArguments(string $command): string
    {
        $what = RequestParameter::getString('what');

        switch ($what) {
            case 'dataonly':
                $command .= ' -a';

                $dFormat = RequestParameter::getString('d_format');
                $dOids = RequestParameter::getString('d_oids');

                if ($dFormat === 'sql') {
                    $command .= ' --inserts';
                } elseif (isset($dOids)) {
                    $command .= ' -o';
                }

                break;

            case 'structureonly':
                $command .= ' -s';

                $sClean = RequestParameter::getString('s_clean');

                if (isset($sClean)) {
                    $command .= ' -c';
                }

                break;

            case 'structureanddata':
                $sdFormat = RequestParameter::getString('sd_format');
                $sdOids = RequestParameter::getString('sd_oids');
                $sdClean = RequestParameter::getString('sd_clean');

                if ($sdFormat === 'sql') {
                    $command .= ' --inserts';
                } elseif (isset($sdOids)) {
                    $command .= ' -o';
                }

                if (isset($sdClean)) {
                    $command .= ' -c';
                }

                break;

            default:
                break;
        }

        return $command;
    }

    private function buildCommand(): string
    {
        $subject = RequestParameter::getString('subject') ?? '';
        $dumpAll = ($subject === 'server');
        $dumpFilename = self::ensurePgDumpFilenameExists($this->server(), $dumpAll);

        $command = escapeshellarg((string)$dumpFilename);
        $command = $this->applySubjectSpecificCommandArguments($command, $subject);
        $command = $this->applyCompressSpecificCommandArguments($command, $dumpAll);
        $command = $this->applyWhatSpecificCommandArguments($command);

        return $command;
    }

    private function server(): Server
    {
        $serverId = RequestParameter::getString('server') ?? '';
        $server = Config::getServerById($serverId);

        if (is_null($server)) {
            throw new ServerNotFoundException("No Server for server ($serverId) found.");
        }

        return $server;
    }

    private function handlePostRequest(): never
    {
        $this->setEnvironmentVariables();
        $this->setHttpHeaders();

        passthru($this->buildCommand());
        die;
    }

    private function setEnvironmentVariables(): void
    {
        $serverId = RequestParameter::getString('server') ?? '';
        $serverSession = Logins::serverSession($serverId, Config::getServers());

        if (is_null($serverSession)) {
            throw new ServerSessionNotFoundException("No ServerSession for server ($serverId) found.");
        }

        putenv('PGPASSWORD=' . $serverSession->Password);
        putenv('PGUSER=' . $serverSession->Username);
        putenv('PGPORT=' . $serverSession->Port->Value);

        $host = (string)$serverSession->Host;

        if ($host !== '') {
            putenv('PGHOST=' . $host);
        }

        $subject = RequestParameter::getString('subject') ?? '';
        $dumpAll = ($subject === 'server');

        if ($dumpAll) {
            return;
        }

        $database = RequestParameter::getString('database') ?? '';
        putenv('PGDATABASE=' . $database);
    }

    private function setHttpHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        $output = RequestParameter::getString('output') ?? '';

        $contentType = match ($output) {
            'download' => 'application/download',
            'gzipped' => 'application/download',
            'show' => 'text/plain',
            default => 'text/plain',
        };
        $contentDisposition = match ($output) {
            'download' => 'attachment; filename=dump.sql',
            'gzipped' => 'attachment; filename=dump.sql.gz',
            default => '',
        };

        header("Content-Type: $contentType");

        if ($contentDisposition !== '') {
            header("Content-Disposition: $contentDisposition");
        }
    }
}
