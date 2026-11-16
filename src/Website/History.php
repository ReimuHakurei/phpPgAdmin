<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, Website, WebsiteComponents};
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\DDD\Repositories\History as HistoryRepository;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;

final class History extends Website
{
    public function __construct()
    {
        parent::__construct();

        $this->scripts['history'] = [
            'src' => 'js/history.js',
        ];
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);

        $serverId = RequestParameter::getString('server') ?? '';
        $selectedDatabase = RequestParameter::getString('database') ?? '';

        $form = $dom->createElement('form');
        $form->setAttribute('method', 'post');

        $serverSession = Logins::serverSession($serverId, Config::getServers());
        $db = PhpPgAdminConnection::createFromServerSession($serverSession);

        $table = $dom->createElement('table');
        $table->setAttribute('style', 'width: 100%;');
        $tBody = $dom->createElement('tbody');
        $tr = $dom->createElement('tr');

        $tdLeft = $dom->createElement('td');
        $labelForServer = $dom->createElement('label');
        $labelForServer->setAttribute('for', 'server');
        $labelForServer->appendChild($dom->createTextNode(_('Server')));
        $serverHelpLink = WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => 'pg.server',
                'server' => $serverId,
            ],
        );
        $serverSelect = $dom->createElement('select');
        $serverSelect->setAttribute('name', 'server');
        $serverSelect->setAttribute('id', 'server');
        $serverSelect->setAttribute('onchange', 'changeServer(event)');
        $servers = Config::getServers();

        foreach ($servers as $server) {
            $options = $dom->createElement('option');
            $options->setAttribute('value', $server->id());

            if ($serverId === $server->id()) {
                $options->setAttribute('selected', 'selected');
            }

            $options->appendChild($dom->createTextNode((string)$server->Name . ' (' . $server->id() . ')'));
            $serverSelect->appendChild($options);
        }

        $tdLeft->appendChild($labelForServer);
        $tdLeft->appendChild($serverHelpLink);
        $tdLeft->appendChild($dom->createTextNode(': '));
        $tdLeft->appendChild($serverSelect);

        $tdRight = $dom->createElement('td');
        $tdRight->setAttribute('style', 'text-align: right;');
        $labelForDatabase = $dom->createElement('label');
        $labelForDatabase->setAttribute('for', 'database');
        $labelForDatabase->appendChild($dom->createTextNode(_('Database')));
        $databaseHelpLink = WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => 'pg.database',
                'server' => $serverId,
            ],
        );
        $databaseSelect = $dom->createElement('select');
        $databaseSelect->setAttribute('name', 'database');
        $databaseSelect->setAttribute('id', 'database');
        $databaseSelect->setAttribute('onchange', 'changeServer(event)');
        $databases = $db?->getDatabases() ?? [];
        $emptyOption = $dom->createElement('option');
        $emptyOption->setAttribute('value', '');
        $emptyOption->appendChild($dom->createTextNode('---'));
        $databaseSelect->appendChild($emptyOption);

        foreach ($databases as $database) {
            $options = $dom->createElement('option');
            $options->setAttribute('value', $database['datname']);

            if ($selectedDatabase === $database['datname']) {
                $options->setAttribute('selected', 'selected');
            }

            $options->appendChild($dom->createTextNode($database['datname']));
            $databaseSelect->appendChild($options);
        }

        $tdRight->appendChild($labelForDatabase);
        $tdRight->appendChild($databaseHelpLink);
        $tdRight->appendChild($dom->createTextNode(': '));
        $tdRight->appendChild($databaseSelect);

        $tr->appendChild($tdLeft);
        $tr->appendChild($tdRight);
        $tBody->appendChild($tr);
        $table->appendChild($tBody);

        $form->appendChild($table);
        $body->appendChild($form);
        $body->appendChild($dom->createElement('br'));

        if ($selectedDatabase !== '') {
            $history = HistoryRepository::getHistory($serverId, $selectedDatabase);
            $historyCount = count($history);

            $body->appendChild(
                $this->buildHistoryTable(
                    $dom,
                    $history,
                    $serverId,
                    $selectedDatabase,
                ),
            );

            if ($historyCount === 0) {
                $pNoHistory = $dom->createElement('p');
                $pNoHistory->appendChild($dom->createTextNode(_('No history.')));
                $body->appendChild($pNoHistory);
            }

            $ulNavLink = $dom->createElement('ul');
            $ulNavLink->setAttribute('class', 'navlink');

            $liRefresh = $dom->createElement('li');
            $aRefresh = $dom->createElement('a');
            $requestUri = '';

            if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
                $requestUri = $_SERVER['REQUEST_URI'];
            }

            $aRefresh->setAttribute('href', $requestUri);
            $aRefresh->appendChild($dom->createTextNode(_('Refresh')));
            $liRefresh->appendChild($aRefresh);

            $ulNavLink->appendChild($liRefresh);

            if ($historyCount > 0) {
                $liDownload = $dom->createElement('li');
                $aDownload = $dom->createElement('a');
                $downloadUrl = 'history_download.php';
                $downloadUrlParams = [
                    'database' => $selectedDatabase,
                    'server' => $serverId,
                ];
                $aDownload->setAttribute('href', $downloadUrl . '?' . http_build_query($downloadUrlParams));
                $aDownload->appendChild($dom->createTextNode(_('Download')));
                $liDownload->appendChild($aDownload);
                $ulNavLink->appendChild($liDownload);

                $liClear = $dom->createElement('li');
                $aClear = $dom->createElement('a');
                $clearUrl = 'history_clear.php';
                $clearUrlParams = [
                    'database' => $selectedDatabase,
                    'server' => $serverId,
                ];
                $aClear->setAttribute('href', $clearUrl . '?' . http_build_query($clearUrlParams));
                $aClear->appendChild($dom->createTextNode(_('Clear history')));
                $liClear->appendChild($aClear);
                $ulNavLink->appendChild($liClear);
            }

            $body->appendChild($ulNavLink);
        } else {
            $pPleaseSelectDatabase = $dom->createElement('p');
            $pPleaseSelectDatabase->appendChild($dom->createTextNode(_('Please, select a database.')));
            $body->appendChild($pPleaseSelectDatabase);
        }

        return $body;
    }

    /**
     * @param array<string, array{'query': string, 'paginate': bool}> $history
     */
    private function buildHistoryTable(
        \DOMDocument $dom,
        array $history,
        string $serverId,
        string $selectedDatabase,
    ): \DOMElement {
        $table = $dom->createElement('table');

        $tHead = $dom->createElement('thead');
        $trHead = $dom->createElement('tr');
        $thQuery = $dom->createElement('th');
        $thQuery->setAttribute('class', 'data');
        $thQuery->appendChild($dom->createTextNode(_('SQL')));
        $trHead->appendChild($thQuery);
        $thPaginate = $dom->createElement('th');
        $thPaginate->setAttribute('class', 'data');
        $thPaginate->appendChild($dom->createTextNode(_('Paginate results')));
        $trHead->appendChild($thPaginate);
        $thActions = $dom->createElement('th');
        $thActions->setAttribute('class', 'data');
        $thActions->setAttribute('colspan', '2');
        $thActions->appendChild($dom->createTextNode(_('Actions')));
        $trHead->appendChild($thActions);
        $tHead->appendChild($trHead);
        $table->appendChild($tHead);

        $tBody = $dom->createElement('tbody');
        $historyCounter = 0;

        foreach ($history as $queryId => $historyData) {
            $historyCounter++;

            $tr = $dom->createElement('tr');
            $tr->setAttribute('class', 'data' . ($historyCounter % 2 !== 0 ? '1' : '2'));
            $tdQuery = $dom->createElement('td');
            $tdQuery->appendChild($dom->createTextNode($historyData['query']));
            $tdPaginate = $dom->createElement('td');
            $tdPaginate->setAttribute('style', 'text-align: center;');
            $tdPaginate->appendChild($dom->createTextNode($historyData['paginate'] ? _('Yes') : _('No')));
            $tdExecute = $dom->createElement('td');
            $tdExecute->setAttribute('class', 'opbutton' . ($historyCounter % 2 !== 0 ? '1' : '2'));
            $executeLink = $dom->createElement('a');
            $executeUrl = 'sql.php';
            $executeUrlParams = [
                'database' => $selectedDatabase,
                'nohistory' => 't',
                'paginate' => $historyData['paginate'] ? 't' : 'f',
                'queryid' => $queryId,
                'server' => $serverId,
                'subject' => 'history',
            ];
            $executeLink->setAttribute('href', $executeUrl . '?' . http_build_query($executeUrlParams));
            $executeLink->setAttribute('target', 'detail');
            $executeLink->appendChild($dom->createTextNode(_('Execute')));
            $tdExecute->appendChild($executeLink);
            $tdDelete = $dom->createElement('td');
            $tdDelete->setAttribute('class', 'opbutton' . ($historyCounter % 2 !== 0 ? '1' : '2'));
            $deleteLink = $dom->createElement('a');
            $deleteUrl = 'history_delete.php';
            $deleteUrlParams = [
                'database' => $selectedDatabase,
                'queryid' => $queryId,
                'server' => $serverId,
            ];
            $deleteLink->setAttribute('href', $deleteUrl . '?' . http_build_query($deleteUrlParams));
            $deleteLink->appendChild($dom->createTextNode(_('Delete')));
            $tdDelete->appendChild($deleteLink);
            $tr->appendChild($tdQuery);
            $tr->appendChild($tdPaginate);
            $tr->appendChild($tdExecute);
            $tr->appendChild($tdDelete);

            $tBody->appendChild($tr);
        }

        $table->appendChild($tBody);

        return $table;
    }
}
