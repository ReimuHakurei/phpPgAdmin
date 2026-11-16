<?php

declare(strict_types=1);

namespace PhpPgAdmin;

use PhpPgAdmin\DDD\Entities\Server;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * @phpstan-type ConfigData=array{
 *  'default_lang'?: string,
 *  'extra_login_security'?: bool,
 *  'extra_session_security'?: bool,
 *  'left_width'?: int,
 *  'owned_only'?: bool,
 *  'servers'?: array<Server>,
 *  'show_advanced'?: bool,
 *  'show_system'?: bool
 * }
 */
final class Config
{
    /**
     * @var array<string>|null
     */
    private static ?array $availableLocales = null;

    /**
     * @var ConfigData|null
     */
    private static ?array $conf = null;

    /**
     * @var array{'locale'?: string, 'theme'?: string}
     */
    private static array $data = [];

    public static function extraLoginSecurity(): bool
    {
        $conf = self::tryGetConfigFileData();

        return ($conf['extra_login_security'] ?? true) === true;
    }

    public static function extraSessionSecurity(): bool
    {
        $conf = self::tryGetConfigFileData();

        return ($conf['extra_session_security'] ?? true) === true;
    }

    /**
     * @return array<string>
     */
    public static function getAvailableLocales(): array
    {
        $localeDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'locale';
        $langDir = dir($localeDir);

        if ($langDir === false) {
            return [];
        }

        self::$availableLocales = [];

        while (($entry = $langDir->read()) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!is_dir($localeDir . DIRECTORY_SEPARATOR . $entry)) {
                continue;
            }

            if (preg_match('/^(?P<language>[a-z]{2})_(?P<region>[A-Z]{2})$/', $entry, $matches)) {
                self::$availableLocales[] = $entry;
            }
        }

        return self::$availableLocales;
    }

    /**
     * @param string|array<string> $icon
     */
    public static function getIcon(string|array $icon): string
    {
        $possiblePaths = [];

        if (is_string($icon)) {
            $theme = self::theme();
            $path = "images/themes/{$theme}/{$icon}";
            $possiblePaths[] = "{$path}.png";
            $possiblePaths[] = "{$path}.gif";
            $path = "images/themes/default/{$icon}";
            $possiblePaths[] = "{$path}.png";
            $possiblePaths[] = "{$path}.gif";
        } else {
            // Icon from plugins
            $path = "plugins/{$icon[0]}/images/{$icon[1]}";
            $possiblePaths[] = "{$path}.png";
            $possiblePaths[] = "{$path}.gif";
        }

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return '';
    }

    /**
     * @return array<Server>
     */
    public static function getServers(): array
    {
        $conf = self::tryGetConfigFileData();

        return $conf['servers'] ?? [];
    }

    /**
     * @param string $serverId Server ID in the format host:port:sslmode
     */
    public static function getServerById(string $serverId): ?Server
    {
        $servers = self::getServers();

        foreach ($servers as $server) {
            if ($serverId === $server->id()) {
                return $server;
            }
        }

        return null;
    }

    public static function leftWidth(): int
    {
        $conf = self::tryGetConfigFileData();

        return $conf['left_width'] ?? 200;
    }

    public static function locale(): string
    {
        if (!isset(self::$data['locale'])) {
            $locale = null;
            $requestLanguage = RequestParameter::getString('language');
            $sessionLanguage = $_SESSION['webdbLanguage'] ?? null;
            $cookieLanguage = $_COOKIE['webdbLanguage'] ?? null;

            if (is_string($requestLanguage) && self::languageIsAvailable($requestLanguage)) {
                $locale = self::getNormalizedLocaleFromLocaleOrLanguage($requestLanguage);

                if (!is_null($locale)) {
                    setcookie(
                        name: 'webdbLanguage',
                        value: $locale,
                        expires_or_options: time() + 31_536_000, // 1 year.
                    );
                }
            }

            if (is_null($locale) && is_string($sessionLanguage) && self::languageIsAvailable($sessionLanguage)) {
                $locale = self::getNormalizedLocaleFromLocaleOrLanguage($sessionLanguage);
            }

            if (is_null($locale) && is_string($cookieLanguage) && self::languageIsAvailable($cookieLanguage)) {
                $locale = self::getNormalizedLocaleFromLocaleOrLanguage($cookieLanguage);
            }

            $conf = self::tryGetConfigFileData();

            if (
                is_null($locale) &&
                isset($conf['default_lang']) &&
                $conf['default_lang'] === 'auto' &&
                isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) &&
                is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'])
            ) {
                // extract acceptable language tags
                // (http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4)
                preg_match_all(
                    '/\s*(?P<language>[a-z]{1,8}(?:-[a-z]{1,8})*)(?:;q=(?P<quality>[01](?:.\d{0,3})?))?\s*(?:,|$)/',
                    strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']),
                    $matches,
                    PREG_SET_ORDER,
                );

                $acceptLanguages = [];

                foreach ($matches as $match) { // $match[1] = language tag, [2] = quality
                    if (!isset($match['quality'])) {
                        $match['quality'] = 1; // Default quality to 1
                    }

                    if ($match['quality'] <= 0 || $match['quality'] > 1) {
                        continue;
                    }

                    if (!self::languageIsAvailable($match['language'])) {
                        continue;
                    }

                    $normalizedLocale = self::getNormalizedLocaleFromLocaleOrLanguage($match['language']);

                    if (is_null($normalizedLocale)) {
                        continue;
                    }

                    $acceptLanguages[$match['quality']] = $normalizedLocale;
                }

                if (!empty($acceptLanguages)) {
                    // Sort acceptable languages by quality
                    krsort($acceptLanguages, SORT_NUMERIC);
                    $locale = self::getNormalizedLocaleFromLocaleOrLanguage(reset($acceptLanguages));
                    unset($acceptLanguages);
                }
            }

            // 5. Otherwise resort to the default set in the config file
            if (
                is_null($locale) &&
                (
                    isset($conf['default_lang']) &&
                    $conf['default_lang'] !== 'auto' &&
                    self::languageIsAvailable($conf['default_lang'])
                )
            ) {
                $locale = self::getNormalizedLocaleFromLocaleOrLanguage($conf['default_lang']);
            }

            // 6. Otherwise, default to english.
            if (is_null($locale)) {
                $locale = 'en_US';
            }

            $_SESSION['webdbLanguage'] = $locale;
            self::$data['locale'] = $locale;
        }

        return self::$data['locale'];
    }

    public static function ownedOnly(): bool
    {
        $conf = self::tryGetConfigFileData();

        return $conf['owned_only'] ?? false;
    }

    /**
     * @param string $serverId Server ID in the format host:port:sslmode
     */
    public static function serverExists(string $serverId): bool
    {
        $servers = self::getServers();

        foreach ($servers as $server) {
            if ($serverId === $server->id()) {
                return true;
            }
        }

        return false;
    }

    public static function showAdvanced(): bool
    {
        $conf = self::tryGetConfigFileData();

        return $conf['show_advanced'] ?? false;
    }

    public static function showSystem(): bool
    {
        $conf = self::tryGetConfigFileData();

        return $conf['show_system'] ?? false;
    }

    public static function theme(): string
    {
        if (!isset(self::$data['theme'])) {
            self::$data['theme'] = 'default';
            $requestServer = RequestParameter::getString('server');
            $cookieTheme = $_COOKIE['ppaTheme'] ?? null;
            $sessionTheme = $_SESSION['ppaTheme'] ?? null;
            $requestTheme = RequestParameter::getString('theme');

            if (is_string($requestServer)) {
                $serverIdTheme = self::tryGetThemeByServerId($requestServer);

                if ($serverIdTheme !== '') {
                    self::$data['theme'] = $serverIdTheme;
                }
            }

            if (is_string($cookieTheme) && Themes::cssExists($cookieTheme)) {
                self::$data['theme'] = $cookieTheme;
            }

            if (is_string($sessionTheme) && Themes::cssExists($sessionTheme)) {
                self::$data['theme'] = $sessionTheme;
            }

            if (is_string($requestTheme) && Themes::cssExists($requestTheme)) {
                setcookie(
                    name: 'ppaTheme',
                    value: $requestTheme,
                    expires_or_options: time() + 31_536_000, // 1 year.
                );
                self::$data['theme'] = $requestTheme;
            }

            $_SESSION['ppaTheme'] = self::$data['theme'];
        }

        return self::$data['theme'];
    }

    private static function getNormalizedLocaleFromLocaleOrLanguage(string $localeOrLanguage): ?string
    {
        if (preg_match('/^(?P<language>[a-z]{2})[_-](?P<region>[A-Z]{2})$/i', $localeOrLanguage, $matches)) {
            return strtolower($matches['language']) . '_' . strtoupper($matches['region']);
        }

        $lowerCasedLocaleOrLanguage = strtolower($localeOrLanguage);
        $languageIdsWithLocales = Language::getAvailableLanguageIdsWithLocales();

        if (isset($languageIdsWithLocales[$lowerCasedLocaleOrLanguage])) {
            return $languageIdsWithLocales[$lowerCasedLocaleOrLanguage];
        }

        return null;
    }

    private static function languageIsAvailable(string $language): bool
    {
        $normalizedLocale = self::getNormalizedLocaleFromLocaleOrLanguage($language);

        if (is_null($normalizedLocale)) {
            return false;
        }

        $availableLocales = self::getAvailableLocales();

        foreach ($availableLocales as $locale) {
            if ($locale === $normalizedLocale) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return ConfigData
     */
    private static function tryGetConfigFileData(): array
    {
        if (is_null(self::$conf)) {
            self::$conf = [];

            $confDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'conf';
            $yamlConfigFile = $confDir . DIRECTORY_SEPARATOR . 'config.yaml';

            if (!file_exists($yamlConfigFile)) {
                $yamlConfigFile = $confDir . DIRECTORY_SEPARATOR . 'config.yml';
            }

            $configYamlFileEnv = getenv('PHPPGADMIN_CONFIG_YAML_FILE');

            if (is_string($configYamlFileEnv) && $configYamlFileEnv !== '') {
                $yamlConfigFile = $confDir . DIRECTORY_SEPARATOR . $configYamlFileEnv;
            }

            if (file_exists($yamlConfigFile)) {
                $yaml = (new YamlParser())->parseFile($yamlConfigFile);

                if (is_array($yaml)) {
                    if (isset($yaml['default_lang']) && is_string($yaml['default_lang'])) {
                        self::$conf['default_lang'] = $yaml['default_lang'];
                    }

                    if (isset($yaml['extra_login_security']) && is_bool($yaml['extra_login_security'])) {
                        self::$conf['extra_login_security'] = $yaml['extra_login_security'];
                    }

                    if (isset($yaml['extra_session_security']) && is_bool($yaml['extra_session_security'])) {
                        self::$conf['extra_session_security'] = $yaml['extra_session_security'];
                    }

                    if (isset($yaml['left_width']) && is_int($yaml['left_width'])) {
                        self::$conf['left_width'] = $yaml['left_width'];
                    }

                    if (isset($yaml['owned_only']) && is_bool($yaml['owned_only'])) {
                        self::$conf['owned_only'] = $yaml['owned_only'];
                    }

                    if (isset($yaml['servers']) && is_array($yaml['servers'])) {
                        self::$conf['servers'] = [];

                        foreach ($yaml['servers'] as $server) {
                            if (!is_array($server)) {
                                continue;
                            }

                            self::$conf['servers'][] = Server::fromArray($server);

                            /*
                            if (isset($server['theme']) && is_array($server['theme'])) {
                                $tmpServer['theme'] = [];
                                if (isset($server['theme']['default']) && is_string($server['theme']['default'])) {
                                    $tmpServer['theme']['default'] = $server['theme']['default'];
                                }
                                if (isset($server['theme']['user']) && is_array($server['theme']['user'])) {
                                    $tmpServer['theme']['user'] = [];
                                    if (
                                        isset($server['theme']['user']['specific_user']) &&
                                        is_string($server['theme']['user']['specific_user'])
                                    ) {
                                        $tmpServer['theme']['user']['specific_user'] =
                                            $server['theme']['user']['specific_user'];
                                    }
                                }
                                if (isset($server['theme']['db']) && is_array($server['theme']['db'])) {
                                    $tmpServer['theme']['db'] = [];
                                    if (
                                        isset($server['theme']['db']['specific_db']) &&
                                        is_string($server['theme']['db']['specific_db'])
                                    ) {
                                        $tmpServer['theme']['db']['specific_db'] =
                                            $server['theme']['db']['specific_db'];
                                    }
                                }
                            }
                            */
                        }
                    }

                    if (isset($yaml['show_advanced']) && is_bool($yaml['show_advanced'])) {
                        self::$conf['show_advanced'] = $yaml['show_advanced'];
                    }

                    if (isset($yaml['show_system']) && is_bool($yaml['show_system'])) {
                        self::$conf['show_system'] = $yaml['show_system'];
                    }
                }
            }
        }

        return self::$conf;
    }

    /**
     * @param string $serverId Server ID in the format host:port:sslmode
     */
    private static function tryGetThemeByServerId(string $serverId): string
    {
        $servers = self::getServers();

        $tmpTheme = '';

        foreach ($servers as $info) {
            if ($serverId !== $info->id()) {
                continue;
            }

            /*
            if (!isset($info['theme']) || !isset($info['theme']['default'])) {
                continue;
            }

            if (Themes::cssExists($info['theme']['default'])) {
                $tmpTheme = $info['theme']['default'];
            }

            if (
                isset($_REQUEST['database'])
                && is_string($_REQUEST['database'])
                && isset($info['theme']['db'])
                && isset($info['theme']['db'][$_REQUEST['database']])
                && Themes::cssExists($info['theme']['db'][$_REQUEST['database']])
            ) {
                $tmpTheme = $info['theme']['db'][$_REQUEST['database']];
            }
            */
        }

        return $tmpTheme;
    }
}
