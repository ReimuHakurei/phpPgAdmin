<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, Website};

final class Browser extends Website
{
    public function __construct()
    {
        parent::__construct();

        $this->scripts['xloadtree/xtree2'] = [
            'src' => 'xloadtree/xtree2.js',
        ];
        $this->scripts['xloadtree/xloadtree2'] = [
            'src' => 'xloadtree/xloadtree2.js',
        ];
    }

    /**
     * The browser tree, which lists all servers, also the ones one is not logged in to.
     */
    public static function requiresServerLogin(): bool
    {
        return false;
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);
        $body->setAttribute('class', 'browser');

        $divLtr = $dom->createElement('div');
        $divLtr->setAttribute('dir', 'ltr');

        $divLogo = $dom->createElement('div');
        $divLogo->setAttribute('class', 'logo');
        $aLogo = $dom->createElement('a', self::APP_NAME);
        $aLogo->setAttribute('href', 'intro.php');
        $aLogo->setAttribute('target', 'detail');
        $divLogo->appendChild($aLogo);

        $divRefreshTree = $dom->createElement('div');
        $divRefreshTree->setAttribute('class', 'refreshTree');
        $aBrowser = $dom->createElement('a');
        $aBrowser->setAttribute('href', 'browser.php');
        $aBrowser->setAttribute('target', 'browser');
        $imgRefresh = $dom->createElement('img');
        $imgRefresh->setAttribute('src', Config::getIcon('Refresh'));
        $imgRefresh->setAttribute('alt', _('Refresh'));
        $imgRefresh->setAttribute('title', _('Refresh'));
        $aBrowser->appendChild($imgRefresh);
        $divRefreshTree->appendChild($aBrowser);

        $script = $dom->createElement('script');
        $script->setAttribute('type', 'text/javascript');
        $scriptContent = PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.rootIcon         = "' . Config::getIcon('Servers') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.openRootIcon     = "' . Config::getIcon('Servers') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.folderIcon       = "";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.openFolderIcon   = "";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.fileIcon         = "";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.iIcon            = "' . Config::getIcon('I') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.lIcon            = "' . Config::getIcon('L') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.lMinusIcon       = "' . Config::getIcon('Lminus') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.lPlusIcon        = "' . Config::getIcon('Lplus') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.tIcon            = "' . Config::getIcon('T') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.tMinusIcon       = "' . Config::getIcon('Tminus') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.tPlusIcon        = "' . Config::getIcon('Tplus') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.blankIcon        = "' . Config::getIcon('blank') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.loadingIcon      = "' . Config::getIcon('Loading') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.loadingText      = "' . _('Loading...') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.errorIcon        = "' . Config::getIcon('ObjectNotFound') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.errorLoadingText = "' . _('Error Loading') . '";' . PHP_EOL;
        $scriptContent .= 'webFXTreeConfig.reloadText       = "' . _('Click to reload') . '";' . PHP_EOL;
        $scriptContent .= PHP_EOL;
        $scriptContent .= '// Set default target frame:' . PHP_EOL;
        $scriptContent .= 'WebFXTreeAbstractNode.prototype.target = "detail";' . PHP_EOL;
        $scriptContent .= PHP_EOL;
        $scriptContent .= '// Disable double click:' . PHP_EOL;
        $scriptContent .= 'WebFXTreeAbstractNode.prototype._ondblclick = function(){};' . PHP_EOL;
        $scriptContent .= PHP_EOL;
        $serversTreeUrl = 'servers-tree.php';
        $scriptContent .= 'const tree = new WebFXLoadTree("' . _('Servers') .
            '", "' . $serversTreeUrl . '", "servers.php");' . PHP_EOL;
        $scriptContent .= PHP_EOL;
        $scriptContent .= 'tree.write();' . PHP_EOL;
        $scriptContent .= 'tree.setExpanded(true);' . PHP_EOL;
        $script->appendChild($dom->createTextNode($scriptContent));

        $divLtr->appendChild($divLogo);
        $divLtr->appendChild($divRefreshTree);
        $divLtr->appendChild($script);

        $body->appendChild($divLtr);

        return $body;
    }

    protected function buildHtmlHead(\DOMDocument $dom): \DOMElement
    {
        $head = parent::buildHtmlHead($dom);

        $style = $dom->createElement('style');
        $style->setAttribute('type', 'text/css');
        $style->appendChild($dom->createTextNode(
            '.webfx-tree-children { background-image: url("' . Config::getIcon('I') . '"); }',
        ));
        $head->appendChild($style);

        return $head;
    }
}
