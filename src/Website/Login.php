<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, Session, Website, WebsiteComponents};
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\DDD\Entities\ServerSession;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;

final class Login extends Website
{
    private const RETURN_URL_PARAMETER = 'loginReturnUrl';

    public function __construct()
    {
        parent::__construct();

        $this->title = _('Login');

        if (self::isLoginFormSent()) {
            $this->handlePostRequest();
        }
    }

    /**
     * Whether the request sends the login form, which goes to login.php. A legacy page that shows the form in place
     * (see libraries/lib.inc.php) was sent a form of its own, e.g. after the login expired.
     */
    public static function isLoginFormSent(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? null) === 'POST' && !self::isShownByALegacyPage();
    }

    /**
     * The login form of a server, where the pages lead to while one is not logged in to it. After the login, it leads
     * back to the page of $returnUrl (see buildReturnUrl()).
     */
    public static function buildUrl(string $serverId, ?string $returnUrl = null): string
    {
        $params = ['server' => $serverId, 'subject' => 'server'];

        if (!is_null($returnUrl)) {
            $params[self::RETURN_URL_PARAMETER] = $returnUrl;
        }

        return './login.php?' . http_build_query($params);
    }

    /**
     * The requested page, to lead back to after the login. Relative, like the links between the pages.
     */
    public static function buildReturnUrl(): ?string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? null;

        // The redirect after the login would not send a posted form again.
        if (($_SERVER['REQUEST_METHOD'] ?? null) !== 'GET' || !is_string($scriptName)) {
            return null;
        }

        $returnUrl = './' . basename($scriptName);
        $queryString = $_SERVER['QUERY_STRING'] ?? '';

        if (is_string($queryString) && $queryString !== '') {
            $returnUrl .= '?' . $queryString;
        }

        return $returnUrl;
    }

    /**
     * Back to the page of $returnUrl, or else to the databases of the server. Only to a page of phpPgAdmin itself,
     * so that a link to the login form cannot lead to another site after the login (open redirect).
     */
    public static function buildUrlAfterLogin(string $serverId, ?string $returnUrl): string
    {
        if (!is_null($returnUrl) && preg_match('/^\.\/[A-Za-z0-9_-]+\.php(\?[!-~]*)?$/D', $returnUrl) === 1) {
            return $returnUrl;
        }

        return './all_db.php?' . http_build_query(['server' => $serverId]);
    }

    /**
     * The login form, which is shown while one is not logged in to the server.
     */
    public static function requiresServerLogin(): bool
    {
        return false;
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);

        $body->appendChild(WebsiteComponents::buildTopBar($dom));
        $body->appendChild(WebsiteComponents::buildTrail($dom));

        $serverId = RequestParameter::getString('server');

        if (is_null($serverId)) {
            throw new \InvalidArgumentException('Parameter "server" is required');
        }

        $server = Config::getServerById($serverId);

        if (is_null($server)) {
            throw new \InvalidArgumentException('Server not found');
        }

        $body->appendChild($this->buildTitle($dom, sprintf(_('Login to %s'), (string)$server->Name)));

        if (!empty($this->message)) {
            $body->appendChild(WebsiteComponents::buildMessage($dom, $this->message));
        }

        $form = $dom->createElement('form');
        $form->setAttribute('id', 'login_form');
        // Also from the legacy pages, which show the form in place. Some, like redirect.php, don't handle a login.
        $form->setAttribute('action', './login.php');
        $form->setAttribute('method', 'post');
        $form->setAttribute('name', 'login_form');
        $inputTypeHiddenSubject = $dom->createElement('input');
        $inputTypeHiddenSubject->setAttribute('type', 'hidden');
        $inputTypeHiddenSubject->setAttribute('name', 'subject');
        $inputTypeHiddenSubject->setAttribute('value', 'server');
        $form->appendChild($inputTypeHiddenSubject);
        $inputTypeHiddenServer = $dom->createElement('input');
        $inputTypeHiddenServer->setAttribute('type', 'hidden');
        $inputTypeHiddenServer->setAttribute('name', 'server');
        $inputTypeHiddenServer->setAttribute('value', $serverId);
        $form->appendChild($inputTypeHiddenServer);
        $inputTypeHiddenLoginServer = $dom->createElement('input');
        $inputTypeHiddenLoginServer->setAttribute('type', 'hidden');
        $inputTypeHiddenLoginServer->setAttribute('name', 'loginServer');
        $inputTypeHiddenLoginServer->setAttribute('value', $serverId);
        $form->appendChild($inputTypeHiddenLoginServer);
        $returnUrl = self::getReturnUrl();

        if (!is_null($returnUrl)) {
            $inputTypeHiddenLoginReturnUrl = $dom->createElement('input');
            $inputTypeHiddenLoginReturnUrl->setAttribute('type', 'hidden');
            $inputTypeHiddenLoginReturnUrl->setAttribute('name', self::RETURN_URL_PARAMETER);
            $inputTypeHiddenLoginReturnUrl->setAttribute('value', $returnUrl);
            $form->appendChild($inputTypeHiddenLoginReturnUrl);
        }

        $table = $dom->createElement('table');
        $table->setAttribute('class', 'navbar');
        $table->setAttribute('border', '0');
        $table->setAttribute('cellpadding', '5');
        $table->setAttribute('cellspacing', '3');
        $tr = $dom->createElement('tr');
        $td = $dom->createElement('td');
        $usernameLabel = $dom->createElement('label');
        $usernameLabel->setAttribute('for', 'loginUsername');
        $usernameLabel->appendChild($dom->createTextNode(_('Username')));
        $td->appendChild($usernameLabel);
        $tr->appendChild($td);
        $td = $dom->createElement('td');
        $username = RequestParameter::getString('loginUsername') ?? '';
        $inputTypeTextLoginUsername = $dom->createElement('input');
        $inputTypeTextLoginUsername->setAttribute('type', 'text');
        $inputTypeTextLoginUsername->setAttribute('id', 'loginUsername');
        $inputTypeTextLoginUsername->setAttribute('name', 'loginUsername');
        $inputTypeTextLoginUsername->setAttribute('value', $username);
        $inputTypeTextLoginUsername->setAttribute('size', '24');
        $td->appendChild($inputTypeTextLoginUsername);
        $tr->appendChild($td);
        $table->appendChild($tr);
        $tr = $dom->createElement('tr');
        $td = $dom->createElement('td');
        $labelPassword = $dom->createElement('label');
        $labelPassword->setAttribute('for', 'loginPassword');
        $labelPassword->appendChild($dom->createTextNode(_('Password')));
        $td->appendChild($labelPassword);
        $tr->appendChild($td);
        $td = $dom->createElement('td');
        $inputTypePasswordLoginPassword = $dom->createElement('input');
        $inputTypePasswordLoginPassword->setAttribute('id', 'loginPassword');
        $inputTypePasswordLoginPassword->setAttribute('type', 'password');
        $inputPasswordName = 'loginPassword_' . hash('sha256', (string)$server->Name);
        $inputTypePasswordLoginPassword->setAttribute('name', $inputPasswordName);
        $inputTypePasswordLoginPassword->setAttribute('size', '24');
        $td->appendChild($inputTypePasswordLoginPassword);
        $tr->appendChild($td);
        $table->appendChild($tr);
        $form->appendChild($table);
        $p = $dom->createElement('p');
        $inputTypeSubmitLoginSubmit = $dom->createElement('input');
        $inputTypeSubmitLoginSubmit->setAttribute('type', 'submit');
        $inputTypeSubmitLoginSubmit->setAttribute('id', 'loginSubmit');
        $inputTypeSubmitLoginSubmit->setAttribute('name', 'loginSubmit');
        $inputTypeSubmitLoginSubmit->setAttribute('value', _('Login'));
        $p->appendChild($inputTypeSubmitLoginSubmit);
        $form->appendChild($p);
        $body->appendChild($form);

        return $body;
    }

    private function handlePostRequest(): void
    {
        $loginServer = RequestParameter::getString('loginServer');

        if (is_null($loginServer)) {
            throw new \InvalidArgumentException('Parameter "loginServer" is required');
        }

        $loginUsername = RequestParameter::getString('loginUsername');

        if (is_null($loginUsername)) {
            throw new \InvalidArgumentException('Parameter "loginUsername" is required');
        }

        $server = Config::getServerById($loginServer);

        if (is_null($server)) {
            throw new \InvalidArgumentException('Server not found');
        }

        $loginPassword = RequestParameter::getString('loginPassword_' . hash('sha256', (string)$server->Name));

        if (is_null($loginPassword)) {
            throw new \InvalidArgumentException('Parameter "loginPassword" is required');
        }

        if (!ServerSession::loginIsAllowed($loginUsername, $loginPassword, Config::extraLoginSecurity())) {
            $this->message = _('Login disallowed for security reasons.');

            return;
        }

        $loginIsValid = PhpPgAdminConnection::loginDataIsValid(
            host: (string)$server->Host,
            port: $server->Port->Value,
            sslmode: $server->SslMode,
            user: $loginUsername,
            password: $loginPassword,
        );

        if (!$loginIsValid) {
            $this->message = _('Login failed');

            return;
        }

        if (!isset($_SESSION['webdbLogin']) || !is_array($_SESSION['webdbLogin'])) {
            $_SESSION['webdbLogin'] = [];
        }

        $_SESSION['webdbLogin'][$loginServer] = [
            'defaultdb' => (string)$server->DefaultDb,
            'desc' => (string)$server->Name,
            'host' => (string)$server->Host,
            'password' => $loginPassword,
            'pg_dumpall_path' => '/usr/bin/pg_dumpall',
            'pg_dump_path' => '/usr/bin/pg_dump',
            //'pgVersion' => $server->Version->Value,
            //'platform' => 'PostgreSQL ' . $server->Version->Value,
            'port' => $server->Port->Value,
            'sslmode' => $server->SslMode->value,
            'username' => $loginUsername,
        ];

        // The browser tree still shows the server as logged out and without its databases.
        Session::requestBrowserReload();

        $returnUrl = RequestParameter::getString(self::RETURN_URL_PARAMETER);
        header('Location: ' . self::buildUrlAfterLogin($loginServer, $returnUrl));
        exit;
    }

    /**
     * The page the login leads back to. A legacy page shows the form in place (see libraries/lib.inc.php), so it is
     * that page. The other pages lead to the form with it (see buildUrl()).
     */
    private static function getReturnUrl(): ?string
    {
        if (self::isShownByALegacyPage()) {
            return self::buildReturnUrl();
        }

        return RequestParameter::getString(self::RETURN_URL_PARAMETER);
    }

    /**
     * Whether a legacy page shows the form in place (see libraries/lib.inc.php), under its own script name.
     */
    private static function isShownByALegacyPage(): bool
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? null;

        return is_string($scriptName) && basename($scriptName) !== 'login.php';
    }
}
