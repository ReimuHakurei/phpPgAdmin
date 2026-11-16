<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpPgAdmin\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    public function testExtraLoginSecurityIsReadFromTheConfiguration(): void
    {
        // Tests\Support\TestEnvironment disables it; the default would be true.
        self::assertFalse(Config::extraLoginSecurity());
    }

    public function testShowAdvancedIsReadFromTheConfiguration(): void
    {
        // Tests\Support\TestEnvironment enables it for the E2E tests of the advanced tabs; the default would be false.
        self::assertTrue(Config::showAdvanced());
    }

    public function testAvailableLocales(): void
    {
        $locales = Config::getAvailableLocales();

        self::assertCount(28, $locales);
        self::assertContains('de_DE', $locales);
        self::assertContains('en_US', $locales);
    }

    #[DataProvider('spellingsOfGerman')]
    public function testTheLanguageOfTheRequestSetsTheLocale(string $language): void
    {
        $_GET['language'] = $language;

        self::assertSame('de_DE', Config::locale());
    }

    public function testAnUnavailableLanguageOfTheRequestIsIgnored(): void
    {
        $_GET['language'] = 'xx_XX';

        self::assertSame('en_US', Config::locale());
    }

    public function testTheLanguageOfTheSessionSetsTheLocale(): void
    {
        $_SESSION['webdbLanguage'] = 'fr_FR';

        self::assertSame('fr_FR', Config::locale());
    }

    public function testTheLanguageOfTheCookieSetsTheLocale(): void
    {
        $_COOKIE['webdbLanguage'] = 'fr_FR';

        self::assertSame('fr_FR', Config::locale());
    }

    public function testTheConfiguredLanguageSetsTheLocale(): void
    {
        self::configureTheLanguage('german');
        // Only "auto" takes the language the browser accepts.
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR';

        self::assertSame('de_DE', Config::locale());
    }

    public function testAnUnavailableConfiguredLanguageIsIgnored(): void
    {
        self::configureTheLanguage('xx_XX');

        self::assertSame('en_US', Config::locale());
    }

    public function testWithoutALanguageTheLocaleIsEnglish(): void
    {
        self::assertSame('en_US', Config::locale());
    }

    public function testTheLanguageOfTheRequestWinsOverTheSession(): void
    {
        $_GET['language'] = 'german';
        $_SESSION['webdbLanguage'] = 'fr_FR';

        self::assertSame('de_DE', Config::locale());
    }

    public function testTheLanguageOfTheSessionWinsOverTheCookie(): void
    {
        $_SESSION['webdbLanguage'] = 'fr_FR';
        $_COOKIE['webdbLanguage'] = 'it_IT';

        self::assertSame('fr_FR', Config::locale());
    }

    #[DataProvider('configuredLanguages')]
    public function testTheLanguageOfTheRequestWinsOverTheConfiguredLanguage(string $configuredLanguage): void
    {
        $_GET['language'] = 'german';
        self::configureTheLanguage($configuredLanguage);
        // With "auto", Config would take the language the browser accepts.
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR';

        self::assertSame('de_DE', Config::locale());
    }

    public function testTheLocaleIsKeptInTheSession(): void
    {
        $_GET['language'] = 'german';

        Config::locale();

        self::assertSame('de_DE', $_SESSION['webdbLanguage']);
    }

    public function testTheThemeOfTheRequestSetsTheTheme(): void
    {
        $_GET['theme'] = 'dark';

        self::assertSame('dark', Config::theme());
    }

    public function testAnUnknownThemeOfTheRequestIsIgnored(): void
    {
        $_GET['theme'] = 'unknown';

        self::assertSame('default', Config::theme());
    }

    public function testTheThemeOfTheCookieSetsTheTheme(): void
    {
        $_COOKIE['ppaTheme'] = 'dark';

        self::assertSame('dark', Config::theme());
    }

    public function testWithoutAThemeTheThemeIsDefault(): void
    {
        self::assertSame('default', Config::theme());
    }

    public function testTheThemeOfTheRequestWinsOverTheSession(): void
    {
        $_GET['theme'] = 'dark';
        $_SESSION['ppaTheme'] = 'gotar';

        self::assertSame('dark', Config::theme());
    }

    public function testTheThemeOfTheSessionWinsOverTheCookie(): void
    {
        $_SESSION['ppaTheme'] = 'gotar';
        $_COOKIE['ppaTheme'] = 'dark';

        self::assertSame('gotar', Config::theme());
    }

    public function testTheThemeIsKeptInTheSession(): void
    {
        $_GET['theme'] = 'dark';

        Config::theme();

        self::assertSame('dark', $_SESSION['ppaTheme']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function spellingsOfGerman(): iterable
    {
        yield 'language' => ['german'];
        yield 'language in capitals' => ['GERMAN'];
        yield 'locale' => ['de_DE'];
        yield 'locale with a hyphen in lower case' => ['de-de'];
        yield 'locale with a hyphen in capitals' => ['DE-DE'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function configuredLanguages(): iterable
    {
        yield 'the language the browser accepts' => ['auto'];
        yield 'another language' => ['fr_FR'];
    }

    protected function setUp(): void
    {
        self::startANewRequest();
    }

    protected function tearDown(): void
    {
        self::startANewRequest();
    }

    /**
     * Config reads the configuration file only once, so this replaces what it read.
     */
    private static function configureTheLanguage(string $language): void
    {
        (new \ReflectionProperty(Config::class, 'conf'))->setValue(null, ['default_lang' => $language]);
    }

    /**
     * Config keeps the configuration, the locale and the theme of the first call for the whole request.
     */
    private static function startANewRequest(): void
    {
        $_COOKIE = [];
        $_GET = [];
        $_SESSION = [];
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        (new \ReflectionProperty(Config::class, 'conf'))->setValue(null, null);
        (new \ReflectionProperty(Config::class, 'data'))->setValue(null, []);
    }
}
