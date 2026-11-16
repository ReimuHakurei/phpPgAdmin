<?php

declare(strict_types=1);

namespace PhpPgAdmin\WebsiteComponents;

use PhpPgAdmin\{Config, TrailSubject, WebsiteComponents};
use PhpPgAdmin\DDD\Entities\ServerSession;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;

abstract class TrailBuilder
{
    public static function buildTrailFor(TrailSubject $subject, \DOMDocument $dom): \DOMElement
    {
        $td = $dom->createElement('td');
        $td->setAttribute('class', 'crumb');

        $a = self::buildLinkFor($subject, $dom);

        $iconUrl = match ($subject) {
            TrailSubject::Database => Config::getIcon('Database'),
            TrailSubject::Role => Config::getIcon('Roles'),
            TrailSubject::Server => Config::getIcon('Servers'),
            TrailSubject::Tablespace => Config::getIcon('Tablespaces'),
        };

        $spanIcon = $dom->createElement('span');
        $spanIcon->setAttribute('class', 'icon');
        $imgIcon = $dom->createElement('img');
        $imgIcon->setAttribute('src', $iconUrl);
        $imgIcon->setAttribute('alt', self::buildLinkTitleFor($subject));
        $spanIcon->appendChild($imgIcon);
        $a->appendChild($spanIcon);

        $spanLabel = $dom->createElement('span', self::buildLabelTextFor($subject));
        $spanLabel->setAttribute('class', 'label');
        $a->appendChild($spanLabel);

        $td->appendChild($a);

        $td->appendChild(self::buildHelpLinkFor($subject, $dom));
        $td->appendChild($dom->createTextNode(': '));

        return $td;
    }

    private static function buildHelpLinkFor(TrailSubject $subject, \DOMDocument $dom): \DOMElement
    {
        $serverId = RequestParameter::getString('server') ?? '';
        $helpUrlParam = match ($subject) {
            TrailSubject::Database => 'pg.database',
            TrailSubject::Role => 'pg.role',
            TrailSubject::Server => 'pg.server',
            TrailSubject::Tablespace => 'pg.tablespace',
        };

        return WebsiteComponents::buildHelpLink(
            dom: $dom,
            url: 'help.php',
            urlParams: [
                'help' => $helpUrlParam,
                'server' => $serverId,
            ],
        );
    }

    private static function buildLabelTextFor(TrailSubject $subject): string
    {
        if ($subject === TrailSubject::Database) {
            return RequestParameter::getString('database') ?? '';
        }

        if ($subject === TrailSubject::Server) {
            $serverId = RequestParameter::getString('server') ?? '';
            $serverSession = ServerSession::fromServerId($serverId, Config::getServers());

            if (!is_null($serverSession)) {
                return (string)$serverSession->Name;
            }
        } elseif ($subject === TrailSubject::Role) {
            return RequestParameter::getString('rolename') ?? '';
        } elseif ($subject === TrailSubject::Tablespace) {
            return RequestParameter::getString('tablespace') ?? '';
        }

        return '';
    }

    private static function buildLinkFor(TrailSubject $subject, \DOMDocument $dom): \DOMElement
    {
        $a = $dom->createElement('a');
        $url = self::buildUrlFor($subject);

        if (!is_null($url)) {
            $a->setAttribute('href', $url);
        }

        $a->setAttribute('title', self::buildLinkTitleFor($subject));

        return $a;
    }

    private static function buildLinkTitleFor(TrailSubject $subject): string
    {
        return match ($subject) {
            TrailSubject::Database => _('Database'),
            TrailSubject::Role => _('Role'),
            TrailSubject::Server => _('Server'),
            TrailSubject::Tablespace => _('Tablespace'),
        };
    }

    private static function buildUrlFor(TrailSubject $subject): ?string
    {
        $serverId = RequestParameter::getString('server') ?? '';

        $script = match ($subject) {
            TrailSubject::Database => 'redirect.php',
            TrailSubject::Role => 'roles.php',
            TrailSubject::Server => 'all_db.php',
            TrailSubject::Tablespace => 'tablespaces.php',
        };
        $scriptParams = match ($subject) {
            // Like the legacy Misc::getTrail(): redirect.php opens the database tab that was used last.
            TrailSubject::Database => [
                'database' => RequestParameter::getString('database') ?? '',
                'server' => $serverId,
                'subject' => 'database',
            ],
            TrailSubject::Role => [
                'action' => 'properties',
                'rolename' => RequestParameter::getString('rolename') ?? '',
                'server' => $serverId,
            ],
            TrailSubject::Server => [
                'server' => $serverId,
            ],
            TrailSubject::Tablespace => [
                'server' => $serverId,
                'tablespace' => RequestParameter::getString('tablespace') ?? '',
            ],
        };

        if ($subject === TrailSubject::Tablespace) {
            return null;
        }

        return $script . '?' . http_build_query($scriptParams);
    }
}
