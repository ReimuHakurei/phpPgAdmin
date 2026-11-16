<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PhpPgAdmin\Config;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Support\{ConfigFileNotWrittenException, TestEnvironment};

/**
 * The legacy pages read a PHP configuration file, the new ones a YAML file. The tests cross from new pages to legacy
 * ones (e.g. via redirect.php), so both test configurations have to describe the same setup.
 */
#[CoversNothing]
final class TestEnvironmentTest extends TestCase
{
    /**
     * @var array<mixed>|null
     */
    private static ?array $legacyConfiguration = null;

    public function testTheLegacyPagesReadTheirOwnTestConfiguration(): void
    {
        $configIncPhpFile = getenv('PHPPGADMIN_CONFIG_INC_PHP_FILE');

        self::assertIsString($configIncPhpFile);
        // Never a developer's own conf/config.inc.php.
        self::assertNotSame('config.inc.php', $configIncPhpFile);
        self::assertFileExists(self::confDir() . DIRECTORY_SEPARATOR . $configIncPhpFile);
    }

    public function testTheLegacyConfigurationHasTheServersOfTheYamlConfiguration(): void
    {
        $expectedServers = [];

        foreach (Config::getServers() as $server) {
            $expectedServers[] = [
                'desc' => (string)$server->Name,
                'host' => (string)$server->Host,
                'port' => $server->Port->Value,
                'sslmode' => $server->SslMode->value,
            ];
        }

        $servers = self::legacyConfiguration()['servers'] ?? null;
        self::assertIsArray($servers);

        $actualServers = [];

        foreach ($servers as $server) {
            self::assertIsArray($server);
            $actualServers[] = [
                'desc' => $server['desc'] ?? null,
                'host' => $server['host'] ?? null,
                'port' => $server['port'] ?? null,
                'sslmode' => $server['sslmode'] ?? null,
            ];
        }

        self::assertSame($expectedServers, $actualServers);
    }

    public function testTheLegacyConfigurationHasTheSettingsOfTheYamlConfiguration(): void
    {
        $conf = self::legacyConfiguration();

        self::assertSame(Config::extraLoginSecurity(), $conf['extra_login_security'] ?? null);
        self::assertSame(Config::showAdvanced(), $conf['show_advanced'] ?? null);
        self::assertSame(Config::showSystem(), $conf['show_system'] ?? null);
    }

    public function testAConfigurationThatCannotBeWrittenFailsWithItsPath(): void
    {
        $configYamlFile = 'missing-directory' . DIRECTORY_SEPARATOR . 'config-test.yml';

        $this->expectException(ConfigFileNotWrittenException::class);
        $this->expectExceptionMessage('Failed to write ' . self::confDir() . DIRECTORY_SEPARATOR . $configYamlFile);

        // file_put_contents() warns about the missing directory before it fails.
        @TestEnvironment::setUp($configYamlFile);
    }

    private static function confDir(): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'conf';
    }

    /**
     * @return array<mixed>
     */
    private static function legacyConfiguration(): array
    {
        // include_once includes the file only once per process, so its configuration is kept for the other tests.
        if (is_null(self::$legacyConfiguration)) {
            $configIncPhpFile = getenv('PHPPGADMIN_CONFIG_INC_PHP_FILE');
            self::assertIsString($configIncPhpFile);

            // Like libraries/lib.inc.php, which includes the file into an empty $conf.
            $conf = [];
            include_once self::confDir() . DIRECTORY_SEPARATOR . $configIncPhpFile;
            self::$legacyConfiguration = $conf;
        }

        return self::$legacyConfiguration;
    }
}
