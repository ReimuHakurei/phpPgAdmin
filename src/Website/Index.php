<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, Website};

final class Index extends Website
{
    /**
     * The frames of the browser tree and of the pages, which belong to no server.
     */
    public static function requiresServerLogin(): bool
    {
        return false;
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);
        $body->setAttribute('style', 'position: absolute; top: 0; bottom: 0; left: 0; right: 0;');

        $div = $dom->createElement('div');
        $div->setAttribute('style', 'display: flex; height: 100%;');

        $leftWidth = Config::leftWidth();
        $browserIFrame = $dom->createElement('iframe');
        $browserIFrame->setAttribute('src', 'browser.php');
        $browserIFrame->setAttribute('style', "width: {$leftWidth}px;");
        $browserIFrame->setAttribute('title', 'browser');
        $browserIFrame->setAttribute('name', 'browser');
        $browserIFrame->setAttribute('id', 'browser');
        $browserIFrame->setAttribute('frameborder', '0');
        $div->appendChild($browserIFrame);

        $separator = $dom->createElement('div');
        $separator->setAttribute('style', 'width: 3px; background-color: #AAA; cursor: ew-resize;');
        $separator->setAttribute('id', 'separator');
        $div->appendChild($separator);

        $detailIFrame = $dom->createElement('iframe');
        $detailIFrame->setAttribute('src', 'intro.php');
        $detailIFrame->setAttribute('style', 'width: 100%;');
        $detailIFrame->setAttribute('name', 'detail');
        $detailIFrame->setAttribute('id', 'detail');
        $detailIFrame->setAttribute('frameborder', '0');
        $div->appendChild($detailIFrame);

        $body->appendChild($div);

        return $body;
    }

    protected function buildHtmlHead(\DOMDocument $dom): \DOMElement
    {
        $head = parent::buildHtmlHead($dom);

        $script = $dom->createElement('script');
        $script->setAttribute('type', 'text/javascript');
        $script->setAttribute('src', 'js/main.js');
        $head->appendChild($script);

        return $head;
    }
}
