<?php

declare(strict_types=1);

namespace PhpPgAdmin\Api\Languages;

use PhpPgAdmin\{Config, Language as Locale, Session};
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\DDD\Entities\ServerSession;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;

/**
 * The Languages node of a database in the browser tree, loaded from languages.php?action=tree.
 */
final class Tree
{
    public function __construct()
    {
        Session::start();

        // Like Website: the node for a database without languages is translated.
        Locale::setLocale(Config::locale());
        bindtextdomain('messages', dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'locale');
    }

    public function outputXmlTree(): void
    {
        $serverId = RequestParameter::getString('server') ?? '';
        $database = RequestParameter::getString('database') ?? '';

        $serverSession = ServerSession::fromServerId($serverId, Config::getServers());
        $connection = $database !== ''
            ? PhpPgAdminConnection::createFromServerSession($serverSession, $database)
            : null;

        $dom = self::buildXmlTree($connection?->getLanguages() ?? []);

        header('Content-Type: text/xml; charset=UTF-8');
        header('Cache-Control: no-cache');
        echo $dom->saveXML();
    }

    /**
     * @param array<\PhpPgAdmin\DDD\ValueObjects\Language> $languages
     */
    public static function buildXmlTree(array $languages): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'utf-8');

        $root = $dom->createElement('tree');

        foreach ($languages as $language) {
            $icon = Config::getIcon('Language');

            $tree = $dom->createElement('tree');
            $tree->setAttribute('text', $language->Name);
            $tree->setAttribute('icon', $icon);
            $tree->setAttribute('openicon', $icon);
            $root->appendChild($tree);
        }

        if (empty($languages)) {
            // Same node as the legacy Misc::printTreeXML(): clicking it reloads the parent node.
            $tree = $dom->createElement('tree');
            $tree->setAttribute('text', _('No objects found.'));
            $tree->setAttribute('onaction', 'tree.getSelected().getParent().reload()');
            $tree->setAttribute('icon', Config::getIcon('ObjectNotFound'));
            $root->appendChild($tree);
        }

        $dom->appendChild($root);

        return $dom;
    }
}
