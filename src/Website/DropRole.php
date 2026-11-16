<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, TrailSubject, Website, WebsiteComponents};
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;

final class DropRole extends Website
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
        $body->appendChild(WebsiteComponents::buildTrail($dom, [TrailSubject::Server, TrailSubject::Role]));

        $rolename = RequestParameter::getString('rolename') ?? '';
        $serverId = RequestParameter::getString('server') ?? '';

        $h2 = $dom->createElement('h2', _('Drop role'));
        $aHelp = WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => 'pg.role.drop',
                'server' => $serverId,
            ],
        );
        $h2->appendChild($aHelp);
        $body->appendChild($h2);

        $form = $dom->createElement('form');
        $form->setAttribute('method', 'post');
        $p = $dom->createElement(
            'p',
            sprintf(_('Are you sure you want to drop the role "%s"?'), $rolename),
        );
        $inputHiddenRolename = $dom->createElement('input');
        $inputHiddenRolename->setAttribute('type', 'hidden');
        $inputHiddenRolename->setAttribute('name', 'rolename');
        $inputHiddenRolename->setAttribute('value', $rolename);
        $inputHiddenServer = $dom->createElement('input');
        $inputHiddenServer->setAttribute('type', 'hidden');
        $inputHiddenServer->setAttribute('name', 'server');
        $inputHiddenServer->setAttribute('value', $serverId);
        $inputSubmit = $dom->createElement('input');
        $inputSubmit->setAttribute('type', 'submit');
        $inputSubmit->setAttribute('name', 'action');
        $inputSubmit->setAttribute('value', _('Drop'));
        $aButtonCancel = $dom->createElement('a', _('Cancel'));
        $aButtonCancel->setAttribute('class', 'button');
        $cancelUrl = 'all_db.php';
        $cancelUrlParams = [
            'server' => $serverId,
            'subject' => 'server',
        ];
        $aButtonCancel->setAttribute('href', $cancelUrl . '?' . http_build_query($cancelUrlParams));

        $form->appendChild($p);
        $form->appendChild($inputHiddenRolename);
        $form->appendChild($inputHiddenServer);
        $form->appendChild($inputSubmit);
        $form->appendChild($dom->createEntityReference('nbsp'));
        $form->appendChild($aButtonCancel);

        $body->appendChild($form);

        return $body;
    }

    private function handlePostRequest(): void
    {
        $rolename = RequestParameter::getString('rolename') ?? '';
        $serverId = RequestParameter::getString('server') ?? '';
        $serverSession = Logins::serverSession($serverId, Config::getServers());

        $redirectUrl = 'roles.php';
        $redirectUrlParams = [
            'message' => _('Role dropped.'),
            'server' => $serverId,
            'subject' => 'server',
        ];

        $db = PhpPgAdminConnection::createFromServerSession($serverSession);

        if (is_null($db) || empty($rolename)) {
            return;
        }

        try {
            $db->dropRole($rolename);
            $redirectUrlParams['message'] = _('Role dropped.');
        } catch (\Throwable $e) {
            $redirectUrlParams['message'] = _('Role drop failed.') . ' - ' . $e->getMessage();
        }

        if (!headers_sent()) {
            header('Location: ' . $redirectUrl . '?' . http_build_query($redirectUrlParams));
            die;
        }
    }
}
