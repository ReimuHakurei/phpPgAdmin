<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, TrailSubject, Website, WebsiteComponents};
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;

final class CreateDb extends Website
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
        $serverSession = Logins::serverSession($serverId, Config::getServers());

        $h2 = $dom->createElement('h2', _('Create database'));
        $aHelp = WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => 'pg.database.create',
                'server' => $serverId,
            ],
        );
        $h2->appendChild($aHelp);
        $body->appendChild($h2);

        $formName = RequestParameter::getString('formName') ?? '';

        if ($this->message !== '') {
            $body->appendChild(WebsiteComponents::buildMessage($dom, $this->message));
        }

        $form = $dom->createElement('form');
        $form->setAttribute('method', 'post');
        $table = $dom->createElement('table');
        $tBody = $dom->createElement('tbody');

        $trName = $dom->createElement('tr');
        $thName = $dom->createElement('th');
        $thName->setAttribute('class', 'data left required');
        $labelName = $dom->createElement('label', _('Name'));
        $labelName->setAttribute('for', 'db-name');
        $thName->appendChild($labelName);
        $tdNameValue = $dom->createElement('td');
        $tdNameValue->setAttribute('class', 'data1');
        $inputName = $dom->createElement('input');
        $inputName->setAttribute('type', 'text');
        $inputName->setAttribute('name', 'formName');
        $inputName->setAttribute('value', $formName);
        $inputName->setAttribute('id', 'db-name');
        $inputName->setAttribute('size', '32');
        $inputName->setAttribute('maxlength', (string)PhpPgAdminConnection::MAX_NAME_LENGTH);
        $tdNameValue->appendChild($inputName);
        $trName->appendChild($thName);
        $trName->appendChild($tdNameValue);
        $tBody->appendChild($trName);

        $trTemplate = $dom->createElement('tr');
        $thTemplate = $dom->createElement('th');
        $thTemplate->setAttribute('class', 'data left required');
        $labelTemplate = $dom->createElement('label', _('Template'));
        $labelTemplate->setAttribute('for', 'db-template');
        $thTemplate->appendChild($labelTemplate);
        $tdTemplateValue = $dom->createElement('td');
        $tdTemplateValue->setAttribute('class', 'data1');
        $selectTemplate = $dom->createElement('select');
        $selectTemplate->setAttribute('name', 'formTemplate');
        $selectTemplate->setAttribute('id', 'db-template');
        $db = PhpPgAdminConnection::createFromServerSession($serverSession);
        $dbs = $db?->getDatabases();

        if (is_iterable($dbs)) {
            $formTemplate = RequestParameter::getString('formTemplate') ?? '';
            $optionTemplate0 = $dom->createElement('option', 'template0');
            $optionTemplate0->setAttribute('value', 'template0');

            if ($formTemplate === 'template0') {
                $optionTemplate0->setAttribute('selected', 'selected');
            }

            $selectTemplate->appendChild($optionTemplate0);
            $optionTemplate1 = $dom->createElement('option', 'template1');
            $optionTemplate1->setAttribute('value', 'template1');

            if ($formTemplate === 'template1' || $formTemplate === '') {
                $optionTemplate1->setAttribute('selected', 'selected');
            }

            $formTemplate1 = RequestParameter::getString('formTemplate') ?? '';

            if ($formTemplate1 === 'template1') {
                $optionTemplate1->setAttribute('selected', 'selected');
            }

            $selectTemplate->appendChild($optionTemplate1);

            foreach ($dbs as $dbData) {
                $dbName = $dbData['datname'];

                if ($dbName === 'template1') {
                    continue;
                }

                $optionTemplate = $dom->createElement('option', $dbName);
                $optionTemplate->setAttribute('value', $dbName);

                if ($formTemplate === $dbName) {
                    $optionTemplate->setAttribute('selected', 'selected');
                }

                $selectTemplate->appendChild($optionTemplate);
            }
        }

        $tdTemplateValue->appendChild($selectTemplate);
        $trTemplate->appendChild($thTemplate);
        $trTemplate->appendChild($tdTemplateValue);
        $tBody->appendChild($trTemplate);

        $trEncoding = $dom->createElement('tr');
        $thEncoding = $dom->createElement('th');
        $thEncoding->setAttribute('class', 'data left required');
        $labelEncoding = $dom->createElement('label', _('Encoding'));
        $labelEncoding->setAttribute('for', 'db-encoding');
        $thEncoding->appendChild($labelEncoding);
        $tdEncodingValue = $dom->createElement('td');
        $tdEncodingValue->setAttribute('class', 'data1');
        $selectEncoding = $dom->createElement('select');
        $selectEncoding->setAttribute('name', 'formEncoding');
        $selectEncoding->setAttribute('id', 'db-encoding');
        $emptyOption = $dom->createElement('option');
        $emptyOption->setAttribute('value', '');
        $selectEncoding->appendChild($emptyOption);
        $formEncoding = RequestParameter::getString('formEncoding') ?? '';

        foreach (PhpPgAdminConnection::CODEMAP as $key => $value) {
            $optionEncoding = $dom->createElement('option', $key);
            $optionEncoding->setAttribute('value', $key);

            if ($formEncoding === $key) {
                $optionEncoding->setAttribute('selected', 'selected');
            }

            $selectEncoding->appendChild($optionEncoding);
        }

        $tdEncodingValue->appendChild($selectEncoding);
        $trEncoding->appendChild($thEncoding);
        $trEncoding->appendChild($tdEncodingValue);
        $tBody->appendChild($trEncoding);

        $formCollate = RequestParameter::getString('formCollate') ?? '';
        $trCollation = $dom->createElement('tr');
        $thCollation = $dom->createElement('th');
        $thCollation->setAttribute('class', 'data left');
        $labelCollation = $dom->createElement('label', _('Collation'));
        $labelCollation->setAttribute('for', 'db-collation');
        $thCollation->appendChild($labelCollation);
        $tdCollationValue = $dom->createElement('td');
        $tdCollationValue->setAttribute('class', 'data1');
        $selectCollation = $dom->createElement('select');
        $selectCollation->setAttribute('name', 'formCollate');
        $selectCollation->setAttribute('id', 'db-collation');
        $emptyOption = $dom->createElement('option');
        $emptyOption->setAttribute('value', '');
        $selectCollation->appendChild($emptyOption);
        $availableCollations = $db?->getAvailableCollations();

        if (!is_null($availableCollations)) {
            foreach ($availableCollations as $collation) {
                $option = $dom->createElement('option', $collation);
                $option->setAttribute('value', $collation);

                if ($formCollate === $collation) {
                    $option->setAttribute('selected', 'selected');
                }

                $selectCollation->appendChild($option);
            }
        }

        $tdCollationValue->appendChild($selectCollation);
        $trCollation->appendChild($thCollation);
        $trCollation->appendChild($tdCollationValue);
        $tBody->appendChild($trCollation);

        $formCType = RequestParameter::getString('formCType') ?? '';
        $trCType = $dom->createElement('tr');
        $thCType = $dom->createElement('th');
        $thCType->setAttribute('class', 'data left');
        $labelCType = $dom->createElement('label', _('Character Type'));
        $labelCType->setAttribute('for', 'db-ctype');
        $thCType->appendChild($labelCType);
        $tdCTypeValue = $dom->createElement('td');
        $tdCTypeValue->setAttribute('class', 'data1');
        $selectCType = $dom->createElement('select');
        $selectCType->setAttribute('name', 'formCType');
        $selectCType->setAttribute('id', 'db-ctype');
        $emptyOption = $dom->createElement('option');
        $emptyOption->setAttribute('value', '');
        $selectCType->appendChild($emptyOption);

        if (!is_null($availableCollations)) {
            foreach ($availableCollations as $collation) {
                $option = $dom->createElement('option', $collation);
                $option->setAttribute('value', $collation);

                if ($formCType === $collation) {
                    $option->setAttribute('selected', 'selected');
                }

                $selectCType->appendChild($option);
            }
        }

        $tdCTypeValue->appendChild($selectCType);
        $trCType->appendChild($thCType);
        $trCType->appendChild($tdCTypeValue);
        $tBody->appendChild($trCType);

        $formComment = RequestParameter::getString('formComment') ?? '';
        $trComment = $dom->createElement('tr');
        $thComment = $dom->createElement('th');
        $thComment->setAttribute('class', 'data left');
        $labelComment = $dom->createElement('label', _('Comment'));
        $labelComment->setAttribute('for', 'db-comment');
        $thComment->appendChild($labelComment);
        $tdCommentValue = $dom->createElement('td');
        $textareaComment = $dom->createElement('textarea');
        $textareaComment->setAttribute('name', 'formComment');
        $textareaComment->setAttribute('id', 'db-comment');
        $textareaComment->setAttribute('cols', '32');
        $textareaComment->setAttribute('rows', '3');
        $textareaComment->appendChild($dom->createTextNode($formComment));
        $tdCommentValue->appendChild($textareaComment);
        $trComment->appendChild($thComment);
        $trComment->appendChild($tdCommentValue);
        $tBody->appendChild($trComment);

        $table->appendChild($tBody);
        $form->appendChild($table);

        $p = $dom->createElement('p');

        $inputHiddenAction = $dom->createElement('input');
        $inputHiddenAction->setAttribute('type', 'hidden');
        $inputHiddenAction->setAttribute('name', 'action');
        $inputHiddenAction->setAttribute('value', 'save_create');

        $inputHiddenServer = $dom->createElement('input');
        $inputHiddenServer->setAttribute('type', 'hidden');
        $inputHiddenServer->setAttribute('name', 'server');
        $inputHiddenServer->setAttribute('value', $serverId);

        $inputSubmit = $dom->createElement('input');
        $inputSubmit->setAttribute('type', 'submit');
        $inputSubmit->setAttribute('value', _('Create'));

        $inputCancel = $dom->createElement('a', _('Cancel'));
        $inputCancel->setAttribute('class', 'button');
        $cancelUrl = 'all_db.php';
        $cancelUrlParams = [
            'server' => $serverId,
            'subject' => 'server',
        ];
        $inputCancel->setAttribute('href', $cancelUrl . '?' . http_build_query($cancelUrlParams));

        $p->appendChild($inputHiddenAction);
        $p->appendChild($inputHiddenServer);
        $p->appendChild($inputSubmit);
        $p->appendChild($dom->createEntityReference('nbsp'));
        $p->appendChild($inputCancel);

        $form->appendChild($p);

        $body->appendChild($form);

        return $body;
    }

    private function handlePostRequest(): void
    {
        $formName = RequestParameter::getString('formName') ?? '';

        if (!empty($formName)) {
            $serverId = RequestParameter::getString('server') ?? '';
            $serverSession = Logins::serverSession($serverId, Config::getServers());
            $db = PhpPgAdminConnection::createFromServerSession($serverSession);

            if (!is_null($db)) {
                $db->createDatabase(
                    database: $formName,
                    encoding: RequestParameter::getString('formEncoding') ?? '',
                    tablespace: RequestParameter::getString('formTablespace') ?? '',
                    comment: RequestParameter::getString('formComment') ?? '',
                    template: RequestParameter::getString('formTemplate') ?? 'template1',
                    lcCollate: RequestParameter::getString('formCollate') ?? '',
                    lcCType: RequestParameter::getString('formCType') ?? '',
                );

                if (!headers_sent()) {
                    $redirectUrl = 'all_db.php';
                    $redirectUrlParams = [
                        'server' => $serverId,
                        'subject' => 'server',
                    ];
                    header('Location: ' . $redirectUrl . '?' . http_build_query($redirectUrlParams));
                    die;
                }

                $this->message = _('Database creation failed.');
            }
        } else {
            $this->message = _('You must give a name for your database.');
        }
    }
}
