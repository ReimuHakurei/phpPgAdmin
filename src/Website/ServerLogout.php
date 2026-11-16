<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\{Website, WebsiteComponents};

final class ServerLogout extends Website
{
    public function __construct()
    {
        parent::__construct();

        $serverId = RequestParameter::getString('id');

        if (
            !is_null($serverId) &&
            isset($_SESSION['webdbLogin']) &&
            is_array($_SESSION['webdbLogin']) &&
            isset($_SESSION['webdbLogin'][$serverId])
        ) {
            unset($_SESSION['webdbLogin'][$serverId]);
        }
    }

    /**
     * The logout, which has its own parameter for the server (like the legacy servers.php?action=logout).
     */
    public static function requiresServerLogin(): bool
    {
        return false;
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = $dom->createElement('body');
        $body->appendChild(WebsiteComponents::buildBrowserReloadScript($dom));

        return $body;
    }

    protected function buildHtmlHead(\DOMDocument $dom): \DOMElement
    {
        $head = parent::buildHtmlHead($dom);

        $redirectUrl = './servers.php';

        if (isset($_SERVER['HTTP_REFERER']) && is_string($_SERVER['HTTP_REFERER'])) {
            $redirectUrl = $_SERVER['HTTP_REFERER'];
        }

        $meta = $dom->createElement('meta');
        $meta->setAttribute('http-equiv', 'refresh');
        $meta->setAttribute('content', '0; url=' . $redirectUrl);
        $head->appendChild($meta);

        return $head;
    }
}
