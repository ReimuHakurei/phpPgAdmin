<?php

declare(strict_types=1);

namespace PhpPgAdmin\Api\Servers;

use PhpPgAdmin\{Config, Session};
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;

final class Tree
{
    public function __construct()
    {
        Session::start();
    }

    public function outputXmlTree(): void
    {
        $domImpl = new \DOMImplementation();
        $dom = $domImpl->createDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->encoding = 'utf-8';

        $logins = isset($_SESSION['webdbLogin']) && is_array($_SESSION['webdbLogin'])
            ? $_SESSION['webdbLogin']
            : [];
        $root = $dom->createElement('tree');

        $serverIdParam = RequestParameter::getString('server');
        $serverSession = !is_null($serverIdParam)
            ? Logins::serverSession($serverIdParam, Config::getServers())
            : null;
        $dbConnection = PhpPgAdminConnection::createFromServerSession($serverSession);

        if (!is_null($serverSession) && !is_null($dbConnection)) {
            $dbs = $dbConnection->getDatabases();

            foreach ($dbs as $dbData) {
                $actionUrl = 'redirect.php';
                $actionUrlParams = [
                    'database' => $dbData['datname'],
                    'server' => $serverSession->id(),
                    'subject' => 'database',
                ];
                $srcUrl = 'database.php';
                $srcUrlParams = $actionUrlParams;
                $srcUrlParams['action'] = 'tree';

                $tree = $dom->createElement('tree');
                $tree->setAttribute('text', $dbData['datname']);
                $tree->setAttribute('action', $actionUrl . '?' . http_build_query($actionUrlParams));
                $tree->setAttribute('src', $srcUrl . '?' . http_build_query($srcUrlParams));
                $tree->setAttribute('icon', Config::getIcon('Database'));
                $tree->setAttribute('openicon', Config::getIcon('Database'));
                $tree->setAttribute('tooltip', $dbData['datcomment']);

                $root->appendChild($tree);
            }
        } else {
            $configuredServers = Config::getServers();

            foreach ($configuredServers as $configuredServer) {
                $tree = $dom->createElement('tree');
                $tree->setAttribute('text', (string)$configuredServer->Name);
                $serverId = $configuredServer->id();
                $actionUrlParams = [
                    'server' => $serverId,
                    'subject' => 'server',
                ];
                $actionUrl = 'redirect.php?' . http_build_query($actionUrlParams);
                $tree->setAttribute('action', $actionUrl);

                $username = '';

                if (
                    isset($logins[$serverId]) &&
                    is_array($logins[$serverId]) &&
                    isset($logins[$serverId]['username']) &&
                    is_string($logins[$serverId]['username'])
                ) {
                    $username = $logins[$serverId]['username'];

                    $srcUrlParams = [
                        'server' => $serverId,
                    ];
                    $srcUrl = 'servers-tree.php?' . http_build_query($srcUrlParams);
                    $tree->setAttribute('src', $srcUrl);
                }

                $iconName = $username !== ''
                    ? 'Server'
                    : 'DisconnectedServer';
                $tree->setAttribute('icon', Config::getIcon($iconName));
                $tree->setAttribute('openicon', Config::getIcon($iconName));
                $tree->setAttribute('tooltip', $serverId);
                $root->appendChild($tree);
            }
        }

        $dom->appendChild($root);

        header('Content-Type: text/xml');
        echo $dom->saveXML();
    }
}
