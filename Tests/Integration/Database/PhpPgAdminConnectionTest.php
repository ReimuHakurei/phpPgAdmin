<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use PhpPgAdmin\Config;
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\DDD\Entities\{Server, ServerSession};
use PhpPgAdmin\DDD\ValueObjects\Server\{Filename, Host, Name, Port};
use PhpPgAdmin\DDD\ValueObjects\ServerSession\{Password, Username};
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestEnvironment;

#[CoversClass(PhpPgAdminConnection::class)]
final class PhpPgAdminConnectionTest extends TestCase
{
    public function testLoginDataIsValidForTheRunningServer(): void
    {
        self::assertTrue(self::loginDataIsValid(self::configuredServer(TestEnvironment::RUNNING_SERVER_DESC)));
    }

    public function testLoginDataIsInvalidForTheNotRunningServer(): void
    {
        // Built here instead of read from the configuration: loading the configuration can log missing pg_dump paths,
        // which would satisfy expectErrorLog() even if loginDataIsValid() logged nothing.
        $server = new Server(
            name: new Name(TestEnvironment::NOT_RUNNING_SERVER_DESC),
            host: new Host(TestEnvironment::NOT_RUNNING_SERVER_HOST),
            port: new Port(TestEnvironment::NOT_RUNNING_SERVER_PORT),
            pgDumpPath: new Filename(__FILE__),
            pgDumpAllPath: new Filename(__FILE__),
        );

        // loginDataIsValid() logs the connection error.
        $this->expectErrorLog();

        self::assertFalse(self::loginDataIsValid($server));
    }

    public function testCreateFromServerSessionConnectsToPostgresByDefault(): void
    {
        self::assertSame('postgres', self::currentDatabase(self::connection()));
    }

    public function testCreateFromServerSessionConnectsToTheGivenDatabase(): void
    {
        self::assertSame('template1', self::currentDatabase(self::connection('template1')));
    }

    public function testCreateFromServerSessionConfiguresTheConnection(): void
    {
        $connection = self::connection('template1');

        self::assertSame('UTF8', self::queryValue($connection, 'SHOW client_encoding'));
        self::assertSame('escape', self::queryValue($connection, 'SHOW bytea_output'));

        $statement = $connection->query('SELECT 1 AS one');
        self::assertNotFalse($statement);
        self::assertSame(['one' => 1], $statement->fetch(), 'Rows are fetched as associative arrays.');
    }

    public function testGetLanguagesListsTheProceduralLanguages(): void
    {
        $languages = self::languagesByName(self::connection()->getLanguages());

        self::assertArrayHasKey('plpgsql', $languages);
        self::assertTrue($languages['plpgsql']->IsTrusted);
        self::assertSame('plpgsql_call_handler', $languages['plpgsql']->CallHandler);

        // show_system is off in the test configuration, which hides the built-in languages.
        self::assertArrayNotHasKey('c', $languages);
        self::assertArrayNotHasKey('internal', $languages);
        self::assertArrayNotHasKey('sql', $languages);
    }

    public function testGetLanguagesWithAllListsTheBuiltInLanguagesToo(): void
    {
        $languages = self::languagesByName(self::connection()->getLanguages(all: true));

        foreach (['c', 'internal', 'plpgsql', 'sql'] as $name) {
            self::assertArrayHasKey($name, $languages);
        }

        self::assertFalse($languages['c']->IsTrusted);
        self::assertTrue($languages['sql']->IsTrusted);
        // regproc shows the missing call handler of a built-in language as "-".
        self::assertSame('-', $languages['sql']->CallHandler);

        $names = array_keys($languages);
        $sortedNames = $names;
        sort($sortedNames, SORT_STRING);
        self::assertSame($sortedNames, $names, 'Languages are sorted by name.');
    }

    private static function connection(?string $database = null): PhpPgAdminConnection
    {
        $serverSession = new ServerSession(
            username: new Username(TestEnvironment::getEnvVar('PHPPGADMIN_TEST_SERVER_USERNAME') ?? 'postgres'),
            password: new Password(TestEnvironment::getEnvVar('PHPPGADMIN_TEST_SERVER_PASSWORD') ?? ''),
            name: new Name(TestEnvironment::RUNNING_SERVER_DESC),
            host: new Host(
                TestEnvironment::getEnvVar('PHPPGADMIN_TEST_SERVER_HOSTNAME')
                    ?? TestEnvironment::RUNNING_SERVER_DEFAULT_HOST,
            ),
            port: new Port(TestEnvironment::RUNNING_SERVER_PORT),
            // Existing files, so Filename does not log a missing pg_dump.
            pgDumpPath: new Filename(__FILE__),
            pgDumpAllPath: new Filename(__FILE__),
        );

        $connection = is_null($database)
            ? PhpPgAdminConnection::createFromServerSession($serverSession)
            : PhpPgAdminConnection::createFromServerSession($serverSession, $database);

        self::assertNotNull($connection);

        return $connection;
    }

    private static function currentDatabase(PhpPgAdminConnection $connection): mixed
    {
        return self::queryValue($connection, 'SELECT current_database()');
    }

    /**
     * @param array<\PhpPgAdmin\DDD\ValueObjects\Language> $languages
     * @return array<string, \PhpPgAdmin\DDD\ValueObjects\Language>
     */
    private static function languagesByName(array $languages): array
    {
        $languagesByName = [];

        foreach ($languages as $language) {
            $languagesByName[$language->Name] = $language;
        }

        return $languagesByName;
    }

    private static function configuredServer(string $description): Server
    {
        foreach (Config::getServers() as $server) {
            if ((string)$server->Name === $description) {
                return $server;
            }
        }

        self::fail("No server \"{$description}\" in the test configuration.");
    }

    private static function loginDataIsValid(Server $server): bool
    {
        return PhpPgAdminConnection::loginDataIsValid(
            host: (string)$server->Host,
            port: $server->Port->Value,
            sslmode: $server->SslMode,
            user: TestEnvironment::getEnvVar('PHPPGADMIN_TEST_SERVER_USERNAME') ?? 'postgres',
            password: TestEnvironment::getEnvVar('PHPPGADMIN_TEST_SERVER_PASSWORD') ?? '',
        );
    }

    private static function queryValue(PhpPgAdminConnection $connection, string $sql): mixed
    {
        $statement = $connection->query($sql);

        self::assertNotFalse($statement);

        return $statement->fetchColumn();
    }
}
