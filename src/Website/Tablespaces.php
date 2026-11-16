<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, TrailSubject, Website, WebsiteComponents};
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;

final class Tablespaces extends Website
{
    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);

        $body->appendChild(WebsiteComponents::buildTopBar($dom));
        $body->appendChild(WebsiteComponents::buildTrail($dom, [TrailSubject::Server]));

        $serverId = RequestParameter::getString('server') ?? '';

        $this->appendTablespacesListPage($body, $serverId);

        return $body;
    }

    private function appendTablespacesListPage(\DOMElement $body, string $serverId): void
    {
        $dom = $body->ownerDocument;

        if (is_null($dom)) {
            return;
        }

        $body->appendChild(WebsiteComponents::buildServerDatabasesTabs($dom, $serverId, 'tablespaces'));

        $serverSession = Logins::serverSession($serverId, Config::getServers());
        $db = PhpPgAdminConnection::createFromServerSession($serverSession);

        $tablespaces = $db?->getTablespaces() ?? [];

        if (empty($tablespaces)) {
            $p = $dom->createElement('p');
            $p->appendChild($dom->createTextNode(_('No tablespaces found.')));
            $body->appendChild($p);
        } else {
            $table = $dom->createElement('table');
            $table->setAttribute('style', 'width: 100%;');

            $tHead = $dom->createElement('thead');
            $tHeadRow = $dom->createElement('tr');

            $thName = $dom->createElement('th');
            $thName->setAttribute('class', 'data');
            $thName->appendChild($dom->createTextNode(_('Name')));
            $thOwner = $dom->createElement('th');
            $thOwner->setAttribute('class', 'data');
            $thOwner->appendChild($dom->createTextNode(_('Owner')));
            $thLocation = $dom->createElement('th');
            $thLocation->setAttribute('class', 'data');
            $thLocation->appendChild($dom->createTextNode(_('Location')));
            $thActions = $dom->createElement('th');
            $thActions->setAttribute('class', 'data');
            $thActions->setAttribute('colspan', '3');
            $thActions->appendChild($dom->createTextNode(_('Actions')));
            $thComment = $dom->createElement('th');
            $thComment->setAttribute('class', 'data');
            $thComment->appendChild($dom->createTextNode(_('Comment')));

            $tHeadRow->appendChild($thName);
            $tHeadRow->appendChild($thOwner);
            $tHeadRow->appendChild($thLocation);
            $tHeadRow->appendChild($thActions);
            $tHeadRow->appendChild($thComment);

            $tHead->appendChild($tHeadRow);

            $tBody = $dom->createElement('tbody');

            foreach ($tablespaces as $index => $tablespace) {
                $dataClass = $index % 2 === 0
                    ? 'data1'
                    : 'data2';
                $opButtonClass = $index % 2 === 0
                    ? 'opbutton1'
                    : 'opbutton2';

                $tr = $dom->createElement('tr');
                $tr->setAttribute('class', $dataClass);

                $tdName = $dom->createElement('td');
                $tdName->appendChild($dom->createTextNode((string)$tablespace->Name));
                $tdOwner = $dom->createElement('td');
                $tdOwner->appendChild($dom->createTextNode((string)$tablespace->Owner));
                $tdLocation = $dom->createElement('td');
                $tdLocation->appendChild($dom->createTextNode((string)$tablespace->Location));
                $tdButtonEdit = $dom->createElement('td');
                $tdButtonEdit->setAttribute('class', $opButtonClass);
                $editLink = $dom->createElement('a');
                $editUrl = 'alter_tablespace.php';
                $editUrlParams = [
                    'server' => $serverId,
                    'tablespace' => (string)$tablespace->Name,
                ];
                $editLink->setAttribute('href', $editUrl . '?' . http_build_query($editUrlParams));
                $editLink->appendChild($dom->createTextNode(_('Alter')));
                $tdButtonEdit->appendChild($editLink);
                $tdButtonDrop = $dom->createElement('td');
                $tdButtonDrop->setAttribute('class', $opButtonClass);
                $dropLink = $dom->createElement('a');
                $dropUrl = 'drop_tablespace.php';
                $dropUrlParams = [
                    'server' => $serverId,
                    'tablespace' => (string)$tablespace->Name,
                ];
                $dropLink->setAttribute('href', $dropUrl . '?' . http_build_query($dropUrlParams));
                $dropLink->appendChild($dom->createTextNode(_('Drop')));
                $tdButtonDrop->appendChild($dropLink);
                $tdButtonPrivileges = $dom->createElement('td');
                $tdButtonPrivileges->setAttribute('class', $opButtonClass);
                $privilegesLink = $dom->createElement('a');
                $privilegesUrl = 'privileges.php';
                $privilegesUrlParams = [
                    'server' => $serverId,
                    'subject' => 'tablespace',
                    'tablespace' => (string)$tablespace->Name,
                ];
                $privilegesLink->setAttribute('href', $privilegesUrl . '?' . http_build_query($privilegesUrlParams));
                $privilegesLink->appendChild($dom->createTextNode(_('Privileges')));
                $tdButtonPrivileges->appendChild($privilegesLink);
                $tdComment = $dom->createElement('td');
                $tdComment->appendChild($dom->createTextNode((string)$tablespace->Comment));

                $tr->appendChild($tdName);
                $tr->appendChild($tdOwner);
                $tr->appendChild($tdLocation);
                $tr->appendChild($tdButtonEdit);
                $tr->appendChild($tdButtonDrop);
                $tr->appendChild($tdButtonPrivileges);
                $tr->appendChild($tdComment);
                $tBody->appendChild($tr);
            }

            $table->appendChild($tHead);
            $table->appendChild($tBody);

            $body->appendChild($table);
        }

        $navLinks = [
            [
                'label' => _('Create tablespace'),
                'url' => 'create_tablespace.php',
                'url-params' => [
                    'server' => $serverId,
                ],
            ],
        ];
        $body->appendChild(WebsiteComponents::buildNavLinks($dom, $navLinks));
    }
}
