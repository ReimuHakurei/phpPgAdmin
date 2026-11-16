<?php

declare(strict_types=1);

namespace PhpPgAdmin;

final class Session
{
    public const NAME = 'PPA_ID';

    private const BROWSER_RELOAD_REQUESTED = 'browserReloadRequested';

    /**
     * Whether an earlier request asked for a reload of the browser tree (see requestBrowserReload()). Answers true
     * only once, so only the next page reloads it.
     */
    public static function consumeBrowserReloadRequest(): bool
    {
        $isRequested = ($_SESSION[self::BROWSER_RELOAD_REQUESTED] ?? null) === true;
        unset($_SESSION[self::BROWSER_RELOAD_REQUESTED]);

        return $isRequested;
    }

    /**
     * Has the next page reload the browser tree, after a change to what it shows (e.g. a login). Unlike the legacy
     * $_reload_browser, it outlasts a redirect.
     */
    public static function requestBrowserReload(): void
    {
        $_SESSION[self::BROWSER_RELOAD_REQUESTED] = true;
    }

    public static function destroy(): void
    {
        if (!ini_get('session.auto_start')) {
            session_name(self::NAME);
            session_start();
        }

        unset($_SESSION);
        session_destroy();
    }

    public static function start(): void
    {
        if (Config::extraSessionSecurity()) {
            if (ini_get('session.auto_start')) {
                $setting = strtolower(ini_get('session.cookie_samesite') ?: '');

                if (session_name() !== self::NAME && $setting !== 'lax' && $setting !== 'strict') {
                    session_destroy();
                    session_name(self::NAME);
                    ini_set('session.cookie_samesite', 'Strict');

                    if (!session_start()) {
                        throw new \RuntimeException('Session could not be started');
                    }
                }
            } elseif (session_status() !== PHP_SESSION_ACTIVE) {
                session_name(self::NAME);
                ini_set('session.cookie_samesite', 'Strict');

                if (!session_start()) {
                    throw new \RuntimeException('Session could not be started');
                }
            }
        } elseif (!ini_get('session.auto_start')) {
            session_name(self::NAME);

            if (!session_start()) {
                throw new \RuntimeException('Session could not be started');
            }
        }
    }
}
