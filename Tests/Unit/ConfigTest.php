<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpPgAdmin\Config;
use PHPUnit\Framework\Attributes\CoversClass;
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

    public function testTheLanguageOfTheRequestSetsTheLocale(): void
    {
        $_GET['language'] = 'german';

        self::assertSame('de_DE', Config::locale());
    }

    public function testTheThemeOfTheRequestSetsTheTheme(): void
    {
        $_GET['theme'] = 'dark';

        self::assertSame('dark', Config::theme());
    }

    protected function setUp(): void
    {
        $_GET = [];
        $_SESSION = [];
        self::forgetTheLocaleAndTheTheme();
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_SESSION = [];
        self::forgetTheLocaleAndTheTheme();
    }

    /**
     * Config keeps the locale and the theme of the first call for the whole request.
     */
    private static function forgetTheLocaleAndTheTheme(): void
    {
        (new \ReflectionProperty(Config::class, 'data'))->setValue(null, []);
    }
}
