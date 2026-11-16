<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, TrailSubject, Website, WebsiteComponents};
use PhpPgAdmin\Application\DTO\Tablespace as DTOTablespace;
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\DDD\ValueObjects\Tablespace;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;

class CreateTablespace extends Website
{
    public function __construct()
    {
        parent::__construct();

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest();
        }
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);

        $body->appendChild(WebsiteComponents::buildTopBar($dom));
        $body->appendChild(WebsiteComponents::buildTrail($dom, [TrailSubject::Server]));

        $serverId = RequestParameter::getString('server') ?? '';

        $h2 = $dom->createElement('h2');
        $h2->appendChild($dom->createTextNode(_('Create tablespace')));
        $helpLink = WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => 'pg.tablespace.create',
                'server' => $serverId,
            ],
        );
        $h2->appendChild($helpLink);
        $body->appendChild($h2);

        $form = $dom->createElement('form');
        $form->setAttribute('method', 'post');

        $inputServer = $dom->createElement('input');
        $inputServer->setAttribute('type', 'hidden');
        $inputServer->setAttribute('name', $serverId);

        $tablespace = null;

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $tablespace = DTOTablespace::createFromFormRequest();
        }

        $form->appendChild($inputServer);
        $form->appendChild(self::buildCreateOrEditTablespaceTable($dom, $serverId, $tablespace));
        $form->appendChild(self::buildCreateOrEditFormParagraphButtonsTable($dom, $serverId));

        $body->appendChild($form);

        return $body;
    }

    protected static function buildCreateOrEditFormParagraphButtonsTable(
        \DOMDocument $dom,
        string $serverId,
        ?Tablespace $tablespace = null,
    ): \DOMElement {
        $p = $dom->createElement('p');

        $inputAction = $dom->createElement('input');
        $inputAction->setAttribute('type', 'hidden');
        $inputAction->setAttribute('name', 'action');
        $inputAction->setAttribute('value', 'save_create');
        $inputServer = $dom->createElement('input');
        $inputServer->setAttribute('type', 'hidden');
        $inputServer->setAttribute('name', 'server');
        $inputServer->setAttribute('value', $serverId);
        $inputSubmitCreate = $dom->createElement('input');
        $inputSubmitCreate->setAttribute('type', 'submit');
        $inputSubmitCreate->setAttribute('name', !is_null($tablespace) ? 'alter' : 'create');
        $inputSubmitCreate->setAttribute('value', !is_null($tablespace) ? _('Alter') : _('Create'));
        $inputSubmitCancel = $dom->createElement('input');
        $inputSubmitCancel->setAttribute('type', 'submit');
        $inputSubmitCancel->setAttribute('name', 'cancel');
        $inputSubmitCancel->setAttribute('value', _('Cancel'));

        $p->appendChild($inputAction);
        $p->appendChild($inputServer);
        $p->appendChild($inputSubmitCreate);
        $p->appendChild($dom->createEntityReference('nbsp'));
        $p->appendChild($inputSubmitCancel);

        $tablespaceName = RequestParameter::getString('tablespace');

        if (!is_null($tablespaceName)) {
            $inputTablespace = $dom->createElement('input');
            $inputTablespace->setAttribute('type', 'hidden');
            $inputTablespace->setAttribute('name', 'tablespace');
            $inputTablespace->setAttribute('value', $tablespaceName);
            $p->appendChild($inputTablespace);
        }

        return $p;
    }

    protected static function buildCreateOrEditTablespaceTable(
        \DOMDocument $dom,
        string $serverId,
        ?Tablespace $tablespace = null,
    ): \DOMElement {
        $table = $dom->createElement('table');
        $table->setAttribute('class', 'form-table');
        $tBody = $dom->createElement('tbody');

        $tablespaceName = '';
        $tablespaceOwner = '';
        $tablespaceLocation = '';
        $tablespaceComment = '';

        if (!is_null($tablespace)) {
            $tablespaceName = (string)$tablespace->Name;
            $tablespaceOwner = (string)$tablespace->Owner;
            $tablespaceLocation = (string)$tablespace->Location;
            $tablespaceComment = (string)$tablespace->Comment;
        }

        $nameSpecs = [
            'id' => Tablespace::FORM_ID_NAME,
            'label-text' => _('Name'),
            'value' => [
                'content' => $tablespaceName,
                'max-length' => 63,
                'required' => true,
            ],
        ];
        $trName = WebsiteComponents::buildTableRowForInputFormular($dom, $nameSpecs);

        $serverSession = Logins::serverSession($serverId, Config::getServers());
        $db = PhpPgAdminConnection::createFromServerSession($serverSession);
        $dbUsers = $db?->getUsers() ?? [];
        $ownerSelectionValues = [];
        $ownerSelectedValues = [];

        if ($tablespaceOwner === '' && !is_null($serverSession)) {
            $tablespaceOwner = (string)$serverSession->Username;
        }

        foreach ($dbUsers as $dbUser) {
            $ownerSelectionValues[] = $dbUser['usename'];

            if ($dbUser['usename'] === $tablespaceOwner) {
                $ownerSelectedValues[] = $tablespaceOwner;
            }
        }

        $ownerSpecs = [
            'id' => Tablespace::FORM_ID_OWNER,
            'label-text' => _('Owner'),
            'value' => [
                'is-multiple' => false,
                'required' => true,
                'selected-values' => $ownerSelectedValues,
                'values' => $ownerSelectionValues,
            ],
        ];
        $trOwner = WebsiteComponents::buildTableRowForSelectionFormular($dom, $ownerSpecs);

        $locationSpecs = [
            'id' => Tablespace::FORM_ID_LOCATION,
            'label-text' => _('Location'),
            'value' => [
                'content' => $tablespaceLocation,
                'readonly' => !is_null($tablespace),
                'required' => true,
            ],
        ];
        $trLocation = WebsiteComponents::buildTableRowForInputFormular($dom, $locationSpecs);

        $commentSpecs = [
            'id' => Tablespace::FORM_ID_COMMENT,
            'label-text' => _('Comment'),
            'value' => [
                'cols' => 32,
                'content' => $tablespaceComment,
                'rows' => 3,
            ],
        ];
        $trComment = WebsiteComponents::buildTableRowForTextareaFormular($dom, $commentSpecs);

        $tBody->appendChild($trName);
        $tBody->appendChild($trOwner);
        $tBody->appendChild($trLocation);
        $tBody->appendChild($trComment);
        $table->appendChild($tBody);

        return $table;
    }

    private function handlePostRequest(): void
    {
        $tablespace = DTOTablespace::createFromFormRequest();

        $serverId = RequestParameter::getString('server') ?? '';
        $serverSession = Logins::serverSession($serverId, Config::getServers());
        $db = PhpPgAdminConnection::createFromServerSession($serverSession);

        if (is_null($db)) {
            return;
        }

        try {
            $db->createTablespace(tablespace: $tablespace);

            if (!headers_sent()) {
                $redirectUrl = 'tablespaces.php';
                $redirectUrlParams = [
                    'message' => _('Tablespace created.'),
                    'server' => $serverId,
                    'subject' => 'server',
                ];
                header('Location: ' . $redirectUrl . '?' . http_build_query($redirectUrlParams));
                die;
            }
        } catch (\PDOException $e) {
            $this->message = _('Tablespace creation failed.');
            $this->pdoException = $e;
        } catch (\Throwable) {
            $this->message = _('Tablespace creation failed.');
        }
    }
}
