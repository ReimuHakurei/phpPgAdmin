<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use PhpPgAdmin\Config;
use PhpPgAdmin\Database\PhpPgAdminConnection;
use PhpPgAdmin\DDD\Entities\{Server, ServerSession};
use PhpPgAdmin\DDD\ValueObjects\Server\{Host, Name, Port};
use PhpPgAdmin\DDD\ValueObjects\ServerSession\{Password, Username};
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestEnvironment;

#[CoversClass(PhpPgAdminConnection::class)]
final class PhpPgAdminConnectionTest extends TestCase
{
    private const USER_PASSWORD = 'ppa_test_user';

    /**
     * The connection that created the user, which drops it again after the test.
     */
    private ?PhpPgAdminConnection $administrator = null;

    public function testLoginDataIsValidForTheRunningServer(): void
    {
        self::assertTrue(self::loginDataIsValid(self::configuredServer(TestEnvironment::RUNNING_SERVER_DESC)));
    }

    public function testLoginDataIsInvalidForTheNotRunningServer(): void
    {
        $server = self::configuredServer(TestEnvironment::NOT_RUNNING_SERVER_DESC);

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

    public function testGetDatabasesWithOwnedOnlyListsOnlyTheDatabasesOfTheUser(): void
    {
        $this->createTheUser();
        $this->createADatabaseAndATemplateOfTheUser();
        self::turnOnOwnedOnly();
        $connection = self::logInAs(self::user(), self::USER_PASSWORD);

        $databases = array_column($connection->getDatabases(), 'datname');

        // show_system is off in the test configuration, which hides the template.
        self::assertSame([self::databaseOfTheUser()], $databases);
    }

    public function testGetDatabasesWithoutOwnedOnlyListsTheDatabasesOfOtherUsersToo(): void
    {
        // owned_only is off in the test configuration.
        $this->createTheUser();
        $connection = self::logInAs(self::user(), self::USER_PASSWORD);

        $databases = array_column($connection->getDatabases(), 'datname');

        self::assertContains('postgres', $databases);
    }

    protected function tearDown(): void
    {
        // Config reads the configuration file again.
        (new \ReflectionProperty(Config::class, 'conf'))->setValue(null, null);

        if (!is_null($this->administrator)) {
            self::dropTheUser($this->administrator);
        }
    }

    /**
     * A role that is no superuser, so owned_only applies to it.
     */
    private function createTheUser(): void
    {
        $administrator = self::connection();
        $this->administrator = $administrator;

        // Removes what an aborted run of this thread left behind.
        self::dropTheUser($administrator);

        $administrator->exec('CREATE ROLE "' . self::user() . '" LOGIN PASSWORD \'' . self::USER_PASSWORD . '\'');
    }

    private function createADatabaseAndATemplateOfTheUser(): void
    {
        self::assertNotNull($this->administrator, 'Create the user first.');

        // template1 cannot be copied while another test is connected to it, template0 takes no connections.
        $options = 'OWNER "' . self::user() . '" TEMPLATE template0';
        $this->administrator->exec('CREATE DATABASE "' . self::databaseOfTheUser() . '" ' . $options);
        $this->administrator->exec(
            'CREATE DATABASE "' . self::templateOfTheUser() . '" ' . $options . ' IS_TEMPLATE true',
        );
    }

    private static function connection(?string $database = null): PhpPgAdminConnection
    {
        $serverSession = self::serverSession(
            TestEnvironment::getEnvVar('PHPPGADMIN_TEST_SERVER_USERNAME') ?? 'postgres',
            TestEnvironment::getEnvVar('PHPPGADMIN_TEST_SERVER_PASSWORD') ?? '',
        );

        $connection = is_null($database)
            ? PhpPgAdminConnection::createFromServerSession($serverSession)
            : PhpPgAdminConnection::createFromServerSession($serverSession, $database);

        self::assertNotNull($connection);

        return $connection;
    }

    /**
     * The quote shows that getDatabases() keeps the name of the user out of its SQL.
     */
    private static function user(): string
    {
        return self::perThread("ppa_test_o'user");
    }

    private static function databaseOfTheUser(): string
    {
        return self::perThread('ppa_test_user_database');
    }

    private static function templateOfTheUser(): string
    {
        return self::perThread('ppa_test_user_template');
    }

    /**
     * Infection runs the tests in parallel threads, which it numbers in TEST_TOKEN, so each thread gets its own role
     * and databases.
     */
    private static function perThread(string $name): string
    {
        $thread = TestEnvironment::getEnvVar('TEST_TOKEN');

        return is_null($thread)
            ? $name
            : "{$name}_{$thread}";
    }

    private static function dropTheUser(PhpPgAdminConnection $administrator): void
    {
        $template = self::templateOfTheUser();
        $templateExists = self::queryValue(
            $administrator,
            "SELECT EXISTS (SELECT FROM pg_database WHERE datname = '{$template}')",
        );

        // A template cannot be dropped.
        if ($templateExists === true) {
            $administrator->exec('ALTER DATABASE "' . $template . '" IS_TEMPLATE false');
        }

        $administrator->exec('DROP DATABASE IF EXISTS "' . $template . '"');
        $administrator->exec('DROP DATABASE IF EXISTS "' . self::databaseOfTheUser() . '"');
        $administrator->exec('DROP ROLE IF EXISTS "' . self::user() . '"');
    }

    /**
     * Config reads the configuration file only once, so this changes what it read.
     */
    private static function turnOnOwnedOnly(): void
    {
        $conf = new \ReflectionProperty(Config::class, 'conf');
        // Reads the configuration file.
        Config::ownedOnly();
        $configuration = $conf->getValue();
        self::assertIsArray($configuration);
        $conf->setValue(null, ['owned_only' => true] + $configuration);
    }

    private static function logInAs(string $username, string $password): PhpPgAdminConnection
    {
        $connection = PhpPgAdminConnection::createFromServerSession(self::serverSession($username, $password));

        self::assertNotNull($connection);

        return $connection;
    }

    private static function serverSession(string $username, string $password): ServerSession
    {
        return new ServerSession(
            username: new Username($username),
            password: new Password($password),
            name: new Name(TestEnvironment::RUNNING_SERVER_DESC),
            host: new Host(
                TestEnvironment::getEnvVar('PHPPGADMIN_TEST_SERVER_HOSTNAME')
                    ?? TestEnvironment::RUNNING_SERVER_DEFAULT_HOST,
            ),
            port: new Port(TestEnvironment::RUNNING_SERVER_PORT),
        );
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
