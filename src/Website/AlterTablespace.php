<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, TrailSubject, Website, WebsiteComponents};
use PhpPgAdmin\Application\DTO\Tablespace as DTOTablespace;
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;

final class AlterTablespace extends CreateTablespace
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
        $body = Website::buildHtmlBody($dom);

        $body->appendChild(WebsiteComponents::buildTopBar($dom));
        $body->appendChild(WebsiteComponents::buildTrail($dom, [TrailSubject::Server, TrailSubject::Tablespace]));

        $serverId = RequestParameter::getString('server') ?? '';
        $tablespaceName = RequestParameter::getString('tablespace');

        $h2 = $dom->createElement('h2');
        $h2->appendChild($dom->createTextNode(_('Alter')));
        $helpLink = WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => 'pg.tablespace.alter',
                'server' => $serverId,
            ],
        );
        $h2->appendChild($helpLink);
        $body->appendChild($h2);

        $form = $dom->createElement('form');
        $form->setAttribute('method', 'post');

        $tablespace = null;

        if (!is_null($tablespaceName)) {
            $serverSession = Logins::serverSession($serverId, Config::getServers());
            $db = PhpPgAdminConnection::createFromServerSession($serverSession);
            $tablespace = $db?->getTablespace($tablespaceName);
        }

        $form->appendChild(self::buildCreateOrEditTablespaceTable($dom, $serverId, $tablespace));
        $form->appendChild(self::buildCreateOrEditFormParagraphButtonsTable($dom, $serverId, $tablespace));

        $body->append($form);

        return $body;
    }

    private function handlePostRequest(): void
    {
        $serverId = RequestParameter::getString('server') ?? '';
        $serverSession = Logins::serverSession($serverId, Config::getServers());

        $db = PhpPgAdminConnection::createFromServerSession($serverSession);

        if (is_null($db)) {
            return;
        }

        try {
            $tablespaceName = RequestParameter::getString('tablespace') ?? '';
            $tablespaceFromForm = DTOTablespace::createFromFormRequest();
            $db->updateTablespace($tablespaceFromForm, $tablespaceName);

            if (!headers_sent()) {
                $redirectUrl = 'tablespaces.php';
                $redirectUrlParams = [
                    'message' => _('Tablespace altered.'),
                    'server' => $serverId,
                    'subject' => 'server',
                ];
                header('Location: ' . $redirectUrl . '?' . http_build_query($redirectUrlParams));
                die;
            }
        } catch (\PDOException $e) {
            $this->message = _('Tablespace alteration failed.');
            $this->pdoException = $e;
        } catch (\Throwable) {
            $this->message = _('Tablespace alteration failed.');
        }
    }
}
