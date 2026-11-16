<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, TrailSubject, Website, WebsiteComponents};
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;

final class AlterDb extends Website
{
    public function __construct()
    {
        parent::__construct();

        if (!(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
            return;
        }

        $serverId = RequestParameter::getString('server') ?? '';

        $oldName = RequestParameter::getString('oldname');
        $newName = RequestParameter::getString('newname');

        if (is_null($oldName) || is_null($newName)) {
            $this->message = _('Database alter failed.');

            return;
        }

        $serverSession = Logins::serverSession($serverId, Config::getServers());
        $db = PhpPgAdminConnection::createFromServerSession($serverSession);

        if (is_null($db)) {
            return;
        }

        try {
            $db->alterDatabase(
                dbName: $oldName,
                newName: $newName,
                newOwner: RequestParameter::getString('owner'),
                comment: RequestParameter::getString('dbcomment'),
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
        } catch (\PDOException $e) {
            $this->message = _('Database alter failed.') . ' - ' . $e->getMessage();
        }
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);

        $body->appendChild(WebsiteComponents::buildTopBar($dom));
        $body->appendChild(WebsiteComponents::buildTrail($dom, [TrailSubject::Server]));

        $database = RequestParameter::getString('database') ?? '';
        $serverId = RequestParameter::getString('server') ?? '';

        $serverSession = Logins::serverSession($serverId, Config::getServers());

        $h2 = $dom->createElement('h2', _('Alter'));
        $aHelp = WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => 'pg.database.alter',
                'server' => $serverId,
            ],
        );
        $h2->appendChild($aHelp);
        $body->appendChild($h2);

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
        $nameLabel = $dom->createElement('label', _('Name'));
        $nameLabel->setAttribute('for', 'newname');
        $thName->appendChild($nameLabel);
        $tdName = $dom->createElement('td');
        $tdName->setAttribute('class', 'data1');
        $inputName = $dom->createElement('input');
        $inputName->setAttribute('type', 'text');
        $inputName->setAttribute('name', 'newname');
        $inputName->setAttribute('id', 'newname');
        $inputName->setAttribute('size', '32');
        $inputName->setAttribute('maxlength', '63');
        $inputName->setAttribute('value', $database);
        $tdName->appendChild($inputName);
        $trName->appendChild($thName);
        $trName->appendChild($tdName);

        $trOwner = $dom->createElement('tr');
        $thOwner = $dom->createElement('th');
        $thOwner->setAttribute('class', 'data left required');
        $ownerLabel = $dom->createElement('label', _('Owner'));
        $ownerLabel->setAttribute('for', 'owner');
        $thOwner->appendChild($ownerLabel);
        $tdOwner = $dom->createElement('td');
        $tdOwner->setAttribute('class', 'data1');
        $selectOwner = $dom->createElement('select');
        $selectOwner->setAttribute('name', 'owner');
        $selectOwner->setAttribute('id', 'owner');
        $db = PhpPgAdminConnection::createFromServerSession($serverSession);
        $dbOwner = $db?->getDatabaseOwner($database);
        $dbUsers = $db?->getUsers();

        if (is_iterable($dbUsers)) {
            foreach ($dbUsers as $dbUser) {
                $username = $dbUser['usename'];
                $optionOwner = $dom->createElement('option', $username);
                $optionOwner->setAttribute('value', $username);

                if ($dbOwner === $username) {
                    $optionOwner->setAttribute('selected', 'selected');
                }

                $selectOwner->appendChild($optionOwner);
            }
        }

        $tdOwner->appendChild($selectOwner);
        $trOwner->appendChild($thOwner);
        $trOwner->appendChild($tdOwner);

        $trComment = $dom->createElement('tr');
        $thComment = $dom->createElement('th');
        $thComment->setAttribute('class', 'data left');
        $commentLabel = $dom->createElement('label', _('Comment'));
        $commentLabel->setAttribute('for', 'dbcomment');
        $thComment->appendChild($commentLabel);
        $tdComment = $dom->createElement('td');
        $tdComment->setAttribute('class', 'data1');
        $textareaComment = $dom->createElement('textarea');
        $textareaComment->setAttribute('name', 'dbcomment');
        $textareaComment->setAttribute('id', 'dbcomment');
        $textareaComment->setAttribute('rows', '3');
        $textareaComment->setAttribute('cols', '32');
        $textareaComment->appendChild($dom->createTextNode($db?->getDatabaseComment($database) ?? ''));
        $tdComment->appendChild($textareaComment);
        $trComment->appendChild($thComment);
        $trComment->appendChild($tdComment);

        $tBody->appendChild($trName);
        $tBody->appendChild($trOwner);
        $tBody->appendChild($trComment);
        $table->appendChild($tBody);

        $form->appendChild($table);

        $inputServer = $dom->createElement('input');
        $inputServer->setAttribute('type', 'hidden');
        $inputServer->setAttribute('name', 'server');
        $inputServer->setAttribute('value', $serverId);
        $form->appendChild($inputServer);
        $inputOldName = $dom->createElement('input');
        $inputOldName->setAttribute('type', 'hidden');
        $inputOldName->setAttribute('name', 'oldname');
        $inputOldName->setAttribute('value', $database);
        $form->appendChild($inputOldName);
        $inputSubmit = $dom->createElement('input');
        $inputSubmit->setAttribute('type', 'submit');
        $inputSubmit->setAttribute('value', _('Alter'));
        $form->appendChild($inputSubmit);

        $inputCancel = $dom->createElement('a', _('Cancel'));
        $inputCancel->setAttribute('class', 'button');
        $cancelUrl = 'all_db.php';
        $cancelUrlParams = [
            'server' => $serverId,
            'subject' => 'server',
        ];
        $inputCancel->setAttribute('href', $cancelUrl . '?' . http_build_query($cancelUrlParams));
        $form->appendChild($dom->createEntityReference('nbsp'));
        $form->appendChild($inputCancel);

        $body->appendChild($form);

        return $body;
    }
}
