<?php

declare(strict_types=1);

namespace Tests\Unit\Website;

use PhpPgAdmin\Website\Login;
use PHPUnit\Framework\Attributes\{CoversMethod, DataProvider};
use PHPUnit\Framework\TestCase;

#[CoversMethod(Login::class, 'buildReturnUrl')]
#[CoversMethod(Login::class, 'buildUrl')]
#[CoversMethod(Login::class, 'buildUrlAfterLogin')]
#[CoversMethod(Login::class, 'isLoginFormSent')]
final class LoginTest extends TestCase
{
    private const SERVER_ID = '127.0.0.1:5432:allow';

    /**
     * @var array<mixed>
     */
    private array $server = [];

    public function testUrlOpensTheLoginFormOfTheServer(): void
    {
        self::assertSame(
            './login.php?server=127.0.0.1%3A5432%3Aallow&subject=server',
            Login::buildUrl(self::SERVER_ID),
        );
    }

    public function testUrlOfTheLoginFormKeepsThePageToLeadBackTo(): void
    {
        self::assertSame(
            './login.php?server=127.0.0.1%3A5432%3Aallow&subject=server'
                . '&loginReturnUrl=.%2Flanguages.php%3Fserver%3D127.0.0.1%253A5432%253Aallow%26database%3Dpostgres',
            Login::buildUrl(self::SERVER_ID, './languages.php?server=127.0.0.1%3A5432%3Aallow&database=postgres'),
        );
    }

    public function testThePageToLeadBackToIsTheRequestedPage(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/languages.php';
        $_SERVER['QUERY_STRING'] = 'server=127.0.0.1%3A5432%3Aallow&database=postgres&subject=database';

        self::assertSame(
            './languages.php?server=127.0.0.1%3A5432%3Aallow&database=postgres&subject=database',
            Login::buildReturnUrl(),
        );
    }

    public function testThePageToLeadBackToIsRelativeAlsoInASubdirectory(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/phpPgAdmin/languages.php';
        $_SERVER['QUERY_STRING'] = 'server=127.0.0.1%3A5432%3Aallow';

        self::assertSame('./languages.php?server=127.0.0.1%3A5432%3Aallow', Login::buildReturnUrl());
    }

    #[DataProvider('noQueryStrings')]
    public function testThePageToLeadBackToHasNoQuestionMarkWithoutAQueryString(?string $queryString): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/database.php';
        unset($_SERVER['QUERY_STRING']);

        if (!is_null($queryString)) {
            $_SERVER['QUERY_STRING'] = $queryString;
        }

        self::assertSame('./database.php', Login::buildReturnUrl());
    }

    public function testAPostedFormIsNoPageToLeadBackTo(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/create_db.php';
        $_SERVER['QUERY_STRING'] = 'server=127.0.0.1%3A5432%3Aallow';

        // The redirect after the login would not send the form again.
        self::assertNull(Login::buildReturnUrl());
    }

    public function testWithoutARequestMethodThereIsNoPageToLeadBackTo(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $_SERVER['SCRIPT_NAME'] = '/languages.php';

        self::assertNull(Login::buildReturnUrl());
    }

    public function testWithoutAScriptThereIsNoPageToLeadBackTo(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['SCRIPT_NAME']);

        self::assertNull(Login::buildReturnUrl());
    }

    #[DataProvider('pagesOfPhpPgAdmin')]
    public function testTheLoginLeadsBackToThePage(string $returnUrl): void
    {
        self::assertSame($returnUrl, Login::buildUrlAfterLogin(self::SERVER_ID, $returnUrl));
    }

    #[DataProvider('noPagesOfPhpPgAdmin')]
    public function testWithoutAPageOfPhpPgAdminTheLoginLeadsToTheDatabasesOfTheServer(?string $returnUrl): void
    {
        self::assertSame(
            './all_db.php?server=127.0.0.1%3A5432%3Aallow',
            Login::buildUrlAfterLogin(self::SERVER_ID, $returnUrl),
        );
    }

    #[DataProvider('scriptsOfTheLoginForm')]
    public function testTheLoginFormIsSentToLoginPhp(?string $scriptName): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['SCRIPT_NAME']);

        if (!is_null($scriptName)) {
            $_SERVER['SCRIPT_NAME'] = $scriptName;
        }

        self::assertTrue(Login::isLoginFormSent());
    }

    /**
     * A legacy page shows the login form in place (see libraries/lib.inc.php), also for a form of its own that is sent
     * after the login expired. The login form itself goes to login.php.
     */
    public function testAFormOfALegacyPageIsNoLoginForm(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/schemas.php';

        self::assertFalse(Login::isLoginFormSent());
    }

    public function testOpeningTheLoginFormDoesNotSendIt(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/login.php';

        self::assertFalse(Login::isLoginFormSent());
    }

    public function testWithoutARequestMethodTheLoginFormIsNotSent(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $_SERVER['SCRIPT_NAME'] = '/login.php';

        self::assertFalse(Login::isLoginFormSent());
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function noQueryStrings(): iterable
    {
        yield 'empty' => [''];
        yield 'none' => [null];
    }

    /**
     * Without a script name, like login.php itself (see getReturnUrl()).
     *
     * @return iterable<string, array{?string}>
     */
    public static function scriptsOfTheLoginForm(): iterable
    {
        yield 'login.php' => ['/login.php'];
        yield 'in a subdirectory' => ['/phpPgAdmin/login.php'];
        yield 'no script name' => [null];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pagesOfPhpPgAdmin(): iterable
    {
        yield 'with a query string' => ['./languages.php?server=127.0.0.1%3A5432%3Aallow&database=postgres'];
        yield 'without a query string' => ['./all_db.php'];
    }

    /**
     * Where a link to the login form must not lead after the login (open redirect).
     *
     * @return iterable<string, array{?string}>
     */
    public static function noPagesOfPhpPgAdmin(): iterable
    {
        yield 'none' => [null];
        yield 'empty' => [''];
        yield 'another site' => ['https://evil.example/'];
        yield 'another site without a scheme' => ['//evil.example/all_db.php'];
        yield 'another site with a path like a page' => ['https://evil.example/./all_db.php'];
        yield 'a script' => ['javascript:alert(1)'];
        yield 'an absolute path' => ['/all_db.php'];
        yield 'a parent directory' => ['./../all_db.php'];
        yield 'no PHP page' => ['./LICENSE'];
        yield 'a header' => ["./all_db.php\r\nSet-Cookie: PPA_ID=evil"];
        yield 'a line break at the end' => ["./all_db.php\n"];
        yield 'a space in the query string' => ['./all_db.php?server=a b'];
    }

    protected function setUp(): void
    {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }
}
