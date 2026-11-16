<?php

declare(strict_types=1);

namespace PhpPgAdmin;

use Locr\Lib\HTTP\StatusCode;
use PhpPgAdmin\DDD\Entities\ServerSession;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;

abstract class Website
{
    public const APP_NAME = 'phpPgAdmin';
    public const APP_VERSION = '8.0.0-prealpha';

    protected string $message = '';
    protected ?\PDOException $pdoException = null;

    /**
     * @var array<string, array{'src': string, 'type'?: 'text/javascript'|'module'}>
     */
    protected array $scripts = [
        'jquery' => [
            'src' => 'libraries/js/jquery-3.7.1.min.js',
        ],
    ];
    protected string $title = '';

    public function __construct()
    {
        if (!($this instanceof Website\Exception)) {
            set_exception_handler([Website\Exception::class, 'handle']);
        }

        Session::start();

        Language::setLocale(Config::locale());
        bindtextdomain('messages', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'locale');

        // After the session is started, which holds the logins. Before the constructors of the pages go on, which
        // handle the posted forms.
        if (static::requiresServerLogin()) {
            self::redirectToLoginUnlessLoggedIn();
        }
    }

    public function buildHtmlDocument(): \DOMDocument
    {
        $domImpl = new \DOMImplementation();
        $doctype = $domImpl->createDocumentType('html', '', '');
        $dom = $domImpl->createDocument('', '', $doctype);
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $root = $dom->createElement('html');
        $root->setAttribute('lang', str_replace('_', '-', Config::locale()));
        $root->appendChild($this->buildHtmlHead($dom));

        $body = $this->buildHtmlBody($dom);

        // At the end of the page, like the legacy Misc::printFooter().
        if (Session::consumeBrowserReloadRequest()) {
            $body->appendChild(WebsiteComponents::buildBrowserReloadScript($dom));
        }

        $root->appendChild($body);
        $dom->appendChild($root);

        return $dom;
    }

    public function buildHtmlString(): string
    {
        $dom = $this->buildHtmlDocument();

        return $dom->saveHTML() ?: '';
    }

    /**
     * Whether the page is only shown while one is logged in to the server of the request. Like the legacy pages, which
     * need a login unless they set $_no_db_connection (see libraries/lib.inc.php).
     */
    public static function requiresServerLogin(): bool
    {
        return true;
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = $dom->createElement('body');

        if (!is_null($this->pdoException)) {
            $body->appendChild($this->buildPdoExceptionMessage($dom, $this->pdoException));
        }

        return $body;
    }

    protected function buildHtmlHead(\DOMDocument $dom): \DOMElement
    {
        $head = $dom->createElement('head');

        $metaContentType = $dom->createElement('meta');
        $metaContentType->setAttribute('http-equiv', 'Content-Type');
        $metaContentType->setAttribute('content', 'text/html; charset=utf-8');
        $head->appendChild($metaContentType);

        $metaColorScheme = $dom->createElement('meta');
        $metaColorScheme->setAttribute('name', 'color-scheme');
        $metaColorScheme->setAttribute('content', 'light dark');
        //$head->appendChild($metaColorScheme);

        $formatTitle = '';

        $formatTitle = !empty($this->title)
            ? self::APP_NAME . ' - ' . $this->title
            : self::APP_NAME;

        $title = $dom->createElement('title');
        $title->appendChild($dom->createTextNode($formatTitle));
        $head->appendChild($title);

        $theme = Config::theme();

        $link = $dom->createElement('link');
        $link->setAttribute('rel', 'stylesheet');
        $link->setAttribute('href', "themes/{$theme}/global.css");
        $link->setAttribute('type', 'text/css');
        $link->setAttribute('id', 'csstheme');
        $head->appendChild($link);

        $link = $dom->createElement('link');
        $link->setAttribute('rel', 'shortcut icon');
        $link->setAttribute('href', "images/themes/{$theme}/Favicon.ico");
        $link->setAttribute('type', 'image/vnd.microsoft.icon');
        $head->appendChild($link);

        $link = $dom->createElement('link');
        $link->setAttribute('rel', 'icon');
        $link->setAttribute('type', 'image/png');
        $link->setAttribute('href', "images/themes/{$theme}/Introduction.png");
        $head->appendChild($link);

        foreach ($this->scripts as $script) {
            $scriptElement = $dom->createElement('script');
            $scriptElement->setAttribute('src', $script['src']);
            $scriptElement->setAttribute('type', $script['type'] ?? 'text/javascript');
            $head->appendChild($scriptElement);
        }

        return $head;
    }

    protected function buildTitle(\DOMDocument $dom, string $title): \DOMElement
    {
        return $dom->createElement('h2', $title);
    }

    protected function buildPdoExceptionMessage(\DOMDocument $dom, \PDOException $exc): \DOMElement
    {
        $table = $dom->createElement('table');
        $table->setAttribute('class', 'error');
        $table->setAttribute('style', 'margin-bottom: 10px; width: 100%;');
        $table->setAttribute('cellpadding', '5');

        $tBody = $dom->createElement('tbody');
        $tr = $dom->createElement('tr');
        $td = $dom->createElement('td');

        $p1 = $dom->createElement('p');
        $p1->setAttribute('style', 'font-weight: bold;');
        $p1->appendChild($dom->createTextNode(_('SQL error:')));

        $sqlErrorMessage = $exc->getMessage();

        if (!is_null($exc->errorInfo)) {
            foreach ($exc->errorInfo as $errorInfoItem) {
                if (is_string($errorInfoItem) && str_starts_with($errorInfoItem, 'ERROR')) {
                    $sqlErrorMessage = $errorInfoItem;

                    break;
                }
            }
        }

        $pre = $dom->createElement('pre');
        $pre->setAttribute('class', 'error');
        $pre->appendChild($dom->createTextNode($sqlErrorMessage));

        $inStatementMessage = 'unknown';

        $excTrace = $exc->getTrace();

        foreach ($excTrace as $traceItem) {
            if (
                isset($traceItem['class']) &&
                $traceItem['class'] === 'PDO' &&
                isset($traceItem['type']) &&
                $traceItem['type'] === '->' &&
                $traceItem['function'] === 'exec' &&
                isset($traceItem['args']) &&
                isset($traceItem['args'][0]) &&
                is_string($traceItem['args'][0])
            ) {
                $inStatementMessage = $traceItem['args'][0];
            }
        }

        $p2 = $dom->createElement('p');
        $span = $dom->createElement('span');
        $span->setAttribute('style', 'font-weight: bold;');
        $span->appendChild($dom->createTextNode(_('In statement:')));
        $p2->appendChild($span);
        $p2->appendChild($dom->createElement('br'));
        $p2->appendChild($dom->createTextNode($inStatementMessage));

        $td->appendChild($p1);
        $td->appendChild($pre);
        $td->appendChild($p2);

        $tr->appendChild($td);
        $tBody->appendChild($tr);

        $table->appendChild($tBody);

        return $table;
    }

    /**
     * The legacy pages show the login form in place. Here a page leads to it, like the browser tree does (see
     * Website\Redirect): showing it in place would need a second page, which would start the session again.
     */
    private static function redirectToLoginUnlessLoggedIn(): void
    {
        $serverId = RequestParameter::getString('server') ?? '';

        if ($serverId === '') {
            throw new \InvalidArgumentException('Missing required parameter: server', StatusCode::BadRequest->value);
        }

        if (!ServerSession::isLoggedIn($serverId)) {
            header('Location: ' . Website\Login::buildUrl($serverId, Website\Login::buildReturnUrl()));
            exit;
        }
    }
}
