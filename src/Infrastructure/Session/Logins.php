<?php

declare(strict_types=1);

namespace PhpPgAdmin\Infrastructure\Session;

use PhpPgAdmin\DDD\Entities\{Server, ServerSession};
use PhpPgAdmin\DDD\ValueObjects\ServerSession\{Password, Platform, Username};

/**
 * The logins to the servers, which Website\Login keeps in the session under webdbLogin, keyed by Server::id().
 */
final class Logins
{
    /**
     * @param array<Server> $servers
     */
    public static function serverSession(string $serverId, array $servers): ?ServerSession
    {
        foreach ($servers as $server) {
            if ($server->id() !== $serverId) {
                continue;
            }

            $username = '';
            $password = '';
            $platform = 'Unknown';

            if (
                isset($_SESSION['webdbLogin']) &&
                is_array($_SESSION['webdbLogin']) &&
                isset($_SESSION['webdbLogin'][$serverId]) &&
                is_array($_SESSION['webdbLogin'][$serverId])
            ) {
                if (
                    isset($_SESSION['webdbLogin'][$serverId]['username']) &&
                    is_string($_SESSION['webdbLogin'][$serverId]['username']) &&
                    isset($_SESSION['webdbLogin'][$serverId]['password']) &&
                    is_string($_SESSION['webdbLogin'][$serverId]['password'])
                ) {
                    $username = $_SESSION['webdbLogin'][$serverId]['username'];
                    $password = $_SESSION['webdbLogin'][$serverId]['password'];
                }

                if (
                    isset($_SESSION['webdbLogin'][$serverId]['platform']) &&
                    is_string($_SESSION['webdbLogin'][$serverId]['platform'])
                ) {
                    $platform = $_SESSION['webdbLogin'][$serverId]['platform'];
                }
            }

            // The password may be empty (trust authentication); ServerSession::loginIsAllowed() decided that at login.
            if ($username !== '') {
                return ServerSession::fromServer(
                    $server,
                    new Username($username),
                    new Password($password),
                    new Platform($platform),
                );
            }
        }

        return null;
    }

    public static function isLoggedIn(string $serverId): bool
    {
        if (!isset($_SESSION['webdbLogin']) || !is_array($_SESSION['webdbLogin'])) {
            return false;
        }

        if (!isset($_SESSION['webdbLogin'][$serverId]) || !is_array($_SESSION['webdbLogin'][$serverId])) {
            return false;
        }

        if (
            !isset($_SESSION['webdbLogin'][$serverId]['username']) ||
            !isset($_SESSION['webdbLogin'][$serverId]['password'])
        ) {
            return false;
        }

        $sessionUsername = $_SESSION['webdbLogin'][$serverId]['username'];
        $sessionPassword = $_SESSION['webdbLogin'][$serverId]['password'];

        if (!is_string($sessionUsername) || !is_string($sessionPassword)) {
            return false;
        }

        return $sessionUsername !== '';
    }
}
