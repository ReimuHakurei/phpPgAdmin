<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, TrailSubject, Website, WebsiteComponents};
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;

final class Roles extends Website
{
    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);

        $trailSubjects = [TrailSubject::Server];
        $rolenameParam = RequestParameter::getString('rolename') ?? '';

        if ($rolenameParam !== '') {
            $trailSubjects[] = TrailSubject::Role;
        }

        $body->appendChild(WebsiteComponents::buildTopBar($dom));
        $body->appendChild(WebsiteComponents::buildTrail($dom, $trailSubjects));

        $action = RequestParameter::getString('action') ?? '';
        $serverId = RequestParameter::getString('server') ?? '';

        match ($action) {
            'properties' => $this->appendPropertiesPage($body, $serverId),
            default => $this->appendRolesListPage($body, $serverId),
        };

        return $body;
    }

    private function appendPropertiesPage(\DOMElement $body, string $serverId): void
    {
        $dom = $body->ownerDocument;

        if (is_null($dom)) {
            return;
        }

        $h2 = $dom->createElement('h2');
        $h2->appendChild($dom->createTextNode(_('Properties')));
        $helpLink = WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => 'pg.role',
                'server' => $serverId,
            ],
        );
        $h2->appendChild($helpLink);
        $body->appendChild($h2);

        $body->appendChild(self::buildPropertiesTable($dom, $serverId));
        $body->appendChild(self::buildPropertiesNavLinkList($dom, $serverId));
    }

    private function appendRolesListPage(\DOMElement $body, string $serverId): void
    {
        $dom = $body->ownerDocument;

        if (is_null($dom)) {
            return;
        }

        $body->appendChild(WebsiteComponents::buildServerDatabasesTabs($dom, $serverId, 'roles'));

        $message = RequestParameter::getString('message') ?? '';

        if (!empty($message)) {
            $body->appendChild(WebsiteComponents::buildMessage($dom, $message));
        }

        $body->appendChild(self::buildRolesTable($dom, $serverId));

        $navLinks = [
            [
                'label' => _('Create role'),
                'url' => 'create_role.php',
                'url-params' => [
                    'server' => $serverId,
                ],
            ],
        ];
        $body->appendChild(WebsiteComponents::buildNavLinks($dom, $navLinks));
    }

    private static function buildPropertiesNavLinkList(\DOMDocument $dom, string $serverId): \DOMElement
    {
        $ul = $dom->createElement('ul');
        $ul->setAttribute('class', 'navlink');

        $liShowAllRoles = $dom->createElement('li');
        $urlParamsShowAllRoles = [
            'server' => $serverId,
        ];
        $urlShowAllRoles = 'roles.php?' . http_build_query($urlParamsShowAllRoles);
        $aShowAllRoles = $dom->createElement('a');
        $aShowAllRoles->setAttribute('href', $urlShowAllRoles);
        $aShowAllRoles->appendChild($dom->createTextNode(_('Show all roles')));
        $liShowAllRoles->appendChild($aShowAllRoles);

        $liAlter = $dom->createElement('li');
        $urlParamsAlter = [
            'rolename' => RequestParameter::getString('rolename') ?? '',
            'server' => $serverId,
        ];
        $urlAlter = 'alter_role.php?' . http_build_query($urlParamsAlter);
        $aAlter = $dom->createElement('a');
        $aAlter->setAttribute('href', $urlAlter);
        $aAlter->appendChild($dom->createTextNode(_('Alter')));
        $liAlter->appendChild($aAlter);

        $liDrop = $dom->createElement('li');
        $urlParamsDrop = [
            'rolename' => RequestParameter::getString('rolename') ?? '',
            'server' => $serverId,
        ];
        $urlDrop = 'drop_role.php?' . http_build_query($urlParamsDrop);
        $aDrop = $dom->createElement('a');
        $aDrop->setAttribute('href', $urlDrop);
        $aDrop->appendChild($dom->createTextNode(_('Drop')));
        $liDrop->appendChild($aDrop);

        $ul->appendChild($liShowAllRoles);
        $ul->appendChild($liAlter);
        $ul->appendChild($liDrop);

        return $ul;
    }

    private static function buildPropertiesTable(\DOMDocument $dom, string $serverId): \DOMElement
    {
        $table = $dom->createElement('table');

        $tHead = $dom->createElement('thead');
        $tHeadRow = $dom->createElement('tr');
        $thDescription = $dom->createElement('th');
        $thDescription->setAttribute('class', 'data');
        $thDescription->setAttribute('style', 'width: 180px;');
        $thDescription->appendChild($dom->createTextNode(_('Description')));
        $thValue = $dom->createElement('th');
        $thValue->setAttribute('class', 'data');
        $thValue->setAttribute('style', 'width: 180px;');
        $thValue->appendChild($dom->createTextNode(_('Value')));
        $tHeadRow->appendChild($thDescription);
        $tHeadRow->appendChild($thValue);

        $rolename = RequestParameter::getString('rolename') ?? '';
        $serverSession = Logins::serverSession($serverId, Config::getServers());
        $db = PhpPgAdminConnection::createFromServerSession($serverSession);
        $role = $db?->getRole($rolename);

        if (is_null($role)) {
            throw new \RuntimeException("Role '{$rolename}' not found.");
        }

        $tableData = [];
        $tableData[] = [
            'description' => _('Name'),
            'value' => $rolename,
        ];
        $tableData[] = [
            'description' => _('Superuser?'),
            'value' => $role->IsSuperuser ? _('Yes') : _('No'),
        ];
        $tableData[] = [
            'description' => _('Create DB?'),
            'value' => $role->CanCreateDb ? _('Yes') : _('No'),
        ];
        $tableData[] = [
            'description' => _('Can create role?'),
            'value' => $role->CanCreateRole ? _('Yes') : _('No'),
        ];
        $tableData[] = [
            'description' => _('Inherits privileges?'),
            'value' => $role->CanInheritRights ? _('Yes') : _('No'),
        ];
        $tableData[] = [
            'description' => _('Can login?'),
            'value' => $role->CanLogin ? _('Yes') : _('No'),
        ];
        $tableData[] = [
            'description' => _('Connection limit'),
            'value' => $role->ConnectionLimit === -1 ? _('No limit') : (string)$role->ConnectionLimit,
        ];
        $tableData[] = [
            'description' => _('Expires'),
            'value' => $role->Expires?->format('c') ?? '',
        ];
        $tableData[] = [
            'description' => _('Session defaults'),
            'value' => 'unhandled',
        ];
        $tableData[] = [
            'description' => _('Member of'),
            'value' => 'unhandled',
        ];
        $tableData[] = [
            'description' => _('Members'),
            'value' => 'unhandled',
        ];
        $tableData[] = [
            'description' => _('Admin members'),
            'value' => 'unhandled',
        ];
        $tBody = $dom->createElement('tbody');

        foreach ($tableData as $dataIndex => $dataRow) {
            $tr = $dom->createElement('tr');

            $tdClassName = 'data' . ($dataIndex % 2 ? '2' : '1');
            $tdDescription = $dom->createElement('td');
            $tdDescription->setAttribute('class', $tdClassName);
            $tdDescription->appendChild($dom->createTextNode($dataRow['description']));
            $tr->appendChild($tdDescription);

            $tdValue = $dom->createElement('td');
            $tdValue->setAttribute('class', $tdClassName);
            $tdValue->appendChild($dom->createTextNode($dataRow['value']));
            $tr->appendChild($tdValue);

            $tBody->appendChild($tr);
        }

        $tHead->appendChild($tHeadRow);
        $table->appendChild($tHead);
        $table->appendChild($tBody);

        return $table;
    }

    private static function buildRolesTable(\DOMDocument $dom, string $serverId): \DOMElement
    {
        $table = $dom->createElement('table');
        $table->setAttribute('style', 'width: 100%;');

        $tHead = $dom->createElement('thead');
        $tHeadRow = $dom->createElement('tr');
        $columns = [
            _('Role'),
            _('Superuser?'),
            _('Create DB?'),
            _('Can create role?'),
            _('Inherits privileges?'),
            _('Can login?'),
            _('Connection limit'),
            _('Expires'),
            _('Actions'),
        ];

        foreach ($columns as $columnIndex => $columnName) {
            $th = $dom->createElement('th');
            $th->setAttribute('class', 'data');

            if ($columnIndex === count($columns) - 1) {
                $th->setAttribute('colspan', '2');
            }

            $th->appendChild($dom->createTextNode($columnName));
            $tHeadRow->appendChild($th);
        }

        $tHead->appendChild($tHeadRow);
        $table->appendChild($tHead);

        $serverSession = Logins::serverSession($serverId, Config::getServers());
        $db = PhpPgAdminConnection::createFromServerSession($serverSession);
        $roles = $db?->getRoles() ?? [];

        $tBody = $dom->createElement('tbody');

        foreach ($roles as $roleIndex => $role) {
            $tr = $dom->createElement('tr');
            $tr->setAttribute('class', 'data' . ($roleIndex % 2 ? '2' : '1'));

            $rolenameUrl = 'roles.php';
            $rolenameUrlParams = [
                'action' => 'properties',
                'rolename' => $role->Name,
                'server' => $serverId,
            ];
            $roleLink = $dom->createElement('a');
            $roleLink->setAttribute('href', $rolenameUrl . '?' . http_build_query($rolenameUrlParams));
            $roleLink->appendChild($dom->createTextNode($role->Name));
            $nameTd = $dom->createElement('td');
            $nameTd->setAttribute('class', 'data');
            $nameTd->appendChild($roleLink);
            $tr->appendChild($nameTd);

            $fields = [
                $role->IsSuperuser ? _('Yes') : _('No'),
                $role->CanCreateDb ? _('Yes') : _('No'),
                $role->CanCreateRole ? _('Yes') : _('No'),
                $role->CanInheritRights ? _('Yes') : _('No'),
                $role->CanLogin ? _('Yes') : _('No'),
                $role->ConnectionLimit === -1 ? _('No limit') : (string)$role->ConnectionLimit,
                $role->Expires?->format('c') ?? '',
            ];

            foreach ($fields as $fieldValue) {
                $td = $dom->createElement('td');
                $td->setAttribute('class', 'data');

                if ($fieldValue === _('Yes') || $fieldValue === _('No')) {
                    $td->setAttribute('style', 'text-align: center;');
                }

                $td->appendChild($dom->createTextNode($fieldValue));
                $tr->appendChild($td);
            }

            $alterTd = $dom->createElement('td');
            $alterTd->setAttribute('class', 'opbutton1');
            $alterLink = $dom->createElement('a');
            $alterUrl = 'alter_role.php';
            $alterUrlParams = [
                'rolename' => $role->Name,
                'server' => $serverId,
            ];
            $alterLink->setAttribute('href', $alterUrl . '?' . http_build_query($alterUrlParams));
            $alterLink->appendChild($dom->createTextNode(_('Alter')));
            $alterTd->appendChild($alterLink);
            $tr->appendChild($alterTd);

            $dropTd = $dom->createElement('td');
            $dropTd->setAttribute('class', 'opbutton1');
            $dropLink = $dom->createElement('a');
            $dropUrl = 'drop_role.php';
            $dropUrlParams = [
                'rolename' => $role->Name,
                'server' => $serverId,
            ];
            $dropLink->setAttribute('href', $dropUrl . '?' . http_build_query($dropUrlParams));
            $dropLink->appendChild($dom->createTextNode(_('Drop')));
            $dropTd->appendChild($dropLink);
            $tr->appendChild($dropTd);

            $tBody->appendChild($tr);
        }

        $table->appendChild($tBody);

        return $table;
    }
}
