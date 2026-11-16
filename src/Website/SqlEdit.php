<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, Website, WebsiteComponents};
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;

final class SqlEdit extends Website
{
    protected string $title = 'SQL';

    public function __construct()
    {
        parent::__construct();

        $this->scripts['sqledit'] = [
            'src' => 'js/sqledit.js',
        ];
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);

        $serverId = RequestParameter::getString('server') ?? '';
        $actionParam = RequestParameter::getString('action') ?? '';

        $body->appendChild(WebsiteComponents::buildServerDatabasesTabs($dom, $serverId, $actionParam));

        $formAction = match ($actionParam) {
            'find' => 'database.php',
            'sql' => 'sql.php',
            default => 'sql.php',
        };

        $form = $dom->createElement('form');
        $form->setAttribute('action', $formAction);
        $form->setAttribute('method', 'post');
        $form->setAttribute('enctype', 'multipart/form-data');
        $form->setAttribute('target', 'detail');

        $table = $dom->createElement('table');
        $table->setAttribute('style', 'width: 100%;');
        $tBody = $dom->createElement('tbody');

        $tr = $dom->createElement('tr');

        $tdServerSelection = $dom->createElement('td');
        $labelForServer = $dom->createElement('label');
        $labelForServer->setAttribute('for', 'server');
        $labelForServer->appendChild($dom->createTextNode(_('Server')));
        $helpLink = WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => 'pg.server',
                'server' => $serverId,
            ],
        );
        $labelForServer->appendChild($helpLink);
        $tdServerSelection->appendChild($labelForServer);
        $tdServerSelection->appendChild($dom->createTextNode(': '));
        $serverSelection = $dom->createElement('select');
        $serverSelection->setAttribute('name', 'server');
        $serverSelection->setAttribute('id', 'server');
        $serverSelection->setAttribute('onchange', 'changeServer(event)');
        $servers = Config::getServers();

        foreach ($servers as $server) {
            $option = $dom->createElement('option');
            $option->setAttribute('value', $server->id());

            if ($server->id() === $serverId) {
                $option->setAttribute('selected', 'selected');
            }

            $option->appendChild($dom->createTextNode($server->Name . ' (' . $server->id() . ')'));
            $serverSelection->appendChild($option);
        }

        $tdServerSelection->appendChild($serverSelection);

        $tdDatabaseSelection = $dom->createElement('td');
        $tdDatabaseSelection->setAttribute('style', 'text-align: right;');
        $labelForDatabase = $dom->createElement('label');
        $labelForDatabase->setAttribute('for', 'database');
        $labelForDatabase->appendChild($dom->createTextNode(_('Database')));
        $helpLink = WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => 'pg.database',
                'server' => $serverId,
            ],
        );
        $labelForDatabase->appendChild($helpLink);
        $labelForDatabase->appendChild($dom->createTextNode(': '));
        $tdDatabaseSelection->appendChild($labelForDatabase);
        $databaseSelection = $dom->createElement('select');
        $databaseSelection->setAttribute('name', 'database');
        $databaseSelection->setAttribute('id', 'database');
        $databaseSelection->setAttribute('onchange', 'changeServer(event)');
        $emptyOption = $dom->createElement('option', '--');
        $emptyOption->setAttribute('value', '');
        $databaseSelection->appendChild($emptyOption);
        $serverSession = Logins::serverSession($serverId, Config::getServers());
        $db = PhpPgAdminConnection::createFromServerSession($serverSession);

        if (!is_null($db)) {
            $databaseParam = RequestParameter::getString('database');

            $dbs = $db->getDatabases();

            foreach ($dbs as $dbMetaData) {
                $option = $dom->createElement('option');
                $option->setAttribute('value', $dbMetaData['datname']);

                if ($dbMetaData['datname'] === $databaseParam) {
                    $option->setAttribute('selected', 'selected');
                }

                $option->appendChild($dom->createTextNode($dbMetaData['datname']));
                $databaseSelection->appendChild($option);
            }
        }

        $tdDatabaseSelection->appendChild($databaseSelection);

        $tr->appendChild($tdServerSelection);
        $tr->appendChild($tdDatabaseSelection);

        $tBody->appendChild($tr);
        $table->appendChild($tBody);

        $form->appendChild($table);

        match ($actionParam) {
            'find' => $this->appendFindPart($form),
            'sql' => $this->appendSqlPart($form, $serverId),
            default => throw new \InvalidArgumentException("Unknown action: {$actionParam}"),
        };

        $body->appendChild($form);

        return $body;
    }

    private function appendFindPart(\DOMElement $element): void
    {
        $dom = $element->ownerDocument;

        if (is_null($dom)) {
            throw new \RuntimeException('DOMDocument is not set for the element.');
        }

        $pFind = $dom->createElement('p');

        $inputTerm = $dom->createElement('input');
        $inputTerm->setAttribute('name', 'term');
        $inputTerm->setAttribute('id', 'term');
        $inputTerm->setAttribute('value', '');
        $inputTerm->setAttribute('size', '32');
        $inputTerm->setAttribute('maxlength', '63');

        $filterSelection = $dom->createElement('select');
        $filterSelection->setAttribute('name', 'filter');
        $filterSelection->setAttribute('id', 'filter');
        $filterArray = [
            '' => _('All objects'),
            'COLUMN' => _('Columns'),
            'CONSTRAINT' => _('Constraints'),
            'DOMAIN' => _('Domains'),
            'FUNCTION' => _('Functions'),
            'INDEX' => _('Indexes'),
            'RULE' => _('Rules'),
            'SCHEMA' => _('Schemas'),
            'SEQUENCE' => _('Sequences'),
            'TABLE' => _('Tables'),
            'TRIGGER' => _('Triggers'),
            'VIEW' => _('Views'),
        ];

        foreach ($filterArray as $key => $value) {
            $option = $dom->createElement('option');
            $option->setAttribute('value', $key);
            $option->appendChild($dom->createTextNode($value));
            $filterSelection->appendChild($option);
        }

        $inputSubmit = $dom->createElement('input');
        $inputSubmit->setAttribute('type', 'submit');
        $inputSubmit->setAttribute('value', _('Find'));

        $inputAction = $dom->createElement('input');
        $inputAction->setAttribute('type', 'hidden');
        $inputAction->setAttribute('name', 'action');
        $inputAction->setAttribute('value', 'find');
        $pFind->appendChild($inputAction);

        $pFind->appendChild($inputTerm);
        $pFind->appendChild($dom->createTextNode(' '));
        $pFind->appendChild($filterSelection);
        $pFind->appendChild($dom->createTextNode(' '));
        $pFind->appendChild($inputSubmit);
        $pFind->appendChild($inputAction);

        $element->appendChild($pFind);
    }

    private function appendSqlPart(\DOMElement $element, string $serverId): void
    {
        $dom = $element->ownerDocument;

        if (is_null($dom)) {
            throw new \RuntimeException('DOMDocument is not set for the element.');
        }

        $pSchemaSearchPath = $dom->createElement('p');
        $labelForSearchPath = $dom->createElement('label');
        $labelForSearchPath->setAttribute('for', 'search_path');
        $labelForSearchPath->appendChild($dom->createTextNode(_('Schema search path')));
        $pSchemaSearchPath->appendChild($labelForSearchPath);
        $searchPathHelp = WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => 'pg.schema.search_path',
                'server' => $serverId,
            ],
        );
        $pSchemaSearchPath->appendChild($searchPathHelp);
        $pSchemaSearchPath->appendChild($dom->createTextNode(': '));
        $inputSearchPath = $dom->createElement('input');
        $inputSearchPath->setAttribute('type', 'text');
        $inputSearchPath->setAttribute('name', 'search_path');
        $inputSearchPath->setAttribute('id', 'search_path');
        $inputSearchPath->setAttribute('size', '50');
        $searchPathParam = RequestParameter::getString('search_path') ?? 'public';
        $inputSearchPath->setAttribute('value', $searchPathParam);
        $pSchemaSearchPath->appendChild($inputSearchPath);

        $textareaQuery = $dom->createElement('textarea');
        $textareaQuery->setAttribute('style', 'width: 98%;');
        $textareaQuery->setAttribute('rows', '10');
        $textareaQuery->setAttribute('cols', '50');
        $textareaQuery->setAttribute('name', 'query');
        $textareaQuery->setAttribute('id', 'query');
        $queryParam = RequestParameter::getString('query') ?? '';
        $textareaQuery->appendChild($dom->createTextNode($queryParam));

        $pUpload = $dom->createElement('p');
        $inputMaxFileSize = $dom->createElement('input');
        $inputMaxFileSize->setAttribute('type', 'hidden');
        $inputMaxFileSize->setAttribute('name', 'MAX_FILE_SIZE');
        $inputMaxFileSize->setAttribute('value', '2097152');
        $labelForScript = $dom->createElement('label');
        $labelForScript->setAttribute('for', 'script');
        $labelForScript->appendChild($dom->createTextNode(_('or upload an SQL script:')));
        $labelForScript->appendChild($dom->createTextNode(' '));
        $inputScript = $dom->createElement('input');
        $inputScript->setAttribute('id', 'script');
        $inputScript->setAttribute('name', 'script');
        $inputScript->setAttribute('type', 'file');
        $pUpload->appendChild($inputMaxFileSize);
        $pUpload->appendChild($labelForScript);
        $pUpload->appendChild($inputScript);

        $pPaginate = $dom->createElement('p');
        $inputPaginate = $dom->createElement('input');
        $inputPaginate->setAttribute('type', 'checkbox');
        $inputPaginate->setAttribute('id', 'paginate');
        $inputPaginate->setAttribute('name', 'paginate');
        $labelForPaginate = $dom->createElement('label');
        $labelForPaginate->setAttribute('for', 'paginate');
        $labelForPaginate->appendChild($dom->createTextNode(_('Paginate results')));
        $pPaginate->appendChild($inputPaginate);
        $pPaginate->appendChild($dom->createTextNode(' '));
        $pPaginate->appendChild($labelForPaginate);

        $pButtons = $dom->createElement('p');
        $inputExecute = $dom->createElement('input');
        $inputExecute->setAttribute('type', 'submit');
        $inputExecute->setAttribute('name', 'execute');
        $inputExecute->setAttribute('accesskey', 'r');
        $inputExecute->setAttribute('value', _('Execute'));
        $inputReset = $dom->createElement('input');
        $inputReset->setAttribute('type', 'reset');
        $inputReset->setAttribute('accesskey', 'q');
        $inputReset->setAttribute('value', _('Reset'));
        $pButtons->appendChild($inputExecute);
        $pButtons->appendChild($dom->createTextNode(' '));
        $pButtons->appendChild($inputReset);

        $element->appendChild($pSchemaSearchPath);
        $element->appendChild($textareaQuery);
        $element->appendChild($pUpload);
        $element->appendChild($pPaginate);
        $element->appendChild($pButtons);
    }
}
