<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\History;
use PhpPgAdmin\Website;

final class HistoryDownload extends Website
{
    public function __construct()
    {
        parent::__construct();

        $serverId = RequestParameter::getString('server') ?? '';
        $selectedDatabase = RequestParameter::getString('database') ?? '';

        $history = History::getHistory($serverId, $selectedDatabase);

        if (!headers_sent()) {
            $now = new \DateTimeImmutable();
            $filename = 'history_' . $now->format('Y-m-d_H-i-s') . '.sql';

            header('Content-Type: application/download');
            header("Content-Disposition: attachment; filename={$filename}");
        }

        foreach ($history as $queries) {
            $query = trim($queries['query']);
            print $query;

            if (substr($query, -1) !== ';') {
                print ';';
            }

            print "\n";
        }

        exit;
    }
}
