<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use Locr\Lib\HTTP\StatusCode;
use PhpPgAdmin\{Config, Website};
use PhpPgAdmin\DDD\Entities\ServerSession;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;

final class Redirect extends Website
{
    public function tryRedirect(): void
    {
        $subject = RequestParameter::getString('subject');

        if (is_null($subject)) {
            throw new \InvalidArgumentException('Missing required parameter: subject', StatusCode::BadRequest->value);
        }

        match ($subject) {
            'root' => $this->redirectToRoot(),
            'server' => $this->redirectToServer(),
            default => trigger_error(
                'Redirecting subject ("' . $subject . '") not found the new way. Continue the old way!',
                E_USER_DEPRECATED,
            )
        };
    }

    /**
     * Leads to the login form itself while one is not logged in to the server (see redirectToServer()).
     */
    public static function requiresServerLogin(): bool
    {
        return false;
    }

    private function redirectToRoot(): void
    {
        $locationUrl = './intro.php';
        header('Location: ' . $locationUrl);
        exit;
    }

    private function redirectToServer(): void
    {
        $server = RequestParameter::getString('server');

        if (is_null($server)) {
            throw new \InvalidArgumentException('Missing required parameter: server', StatusCode::BadRequest->value);
        }

        if (!Config::serverExists($server)) {
            throw new \InvalidArgumentException(
                _('Attempt to connect with invalid server parameter, possibly someone is trying to hack your system.'),
                StatusCode::BadRequest->value,
            );
        }

        $locationUrl = ServerSession::isLoggedIn($server)
            ? './all_db.php?' . http_build_query(['server' => $server])
            : Login::buildUrl($server);

        header('Location: ' . $locationUrl);
        exit;
    }
}
