<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpPgAdmin\{Session, Website};
use PhpPgAdmin\Website\{
    AllDb,
    AllDbExport,
    AlterDb,
    AlterRole,
    AlterTablespace,
    Browser,
    CreateDb,
    CreateRole,
    CreateTablespace,
    DbExport,
    DropDb,
    DropRole,
    DropTablespace,
    History,
    HistoryClear,
    HistoryDelete,
    HistoryDownload,
    Index,
    Intro,
    Languages,
    Login,
    Redirect,
    Roles,
    ServerLogout,
    Servers,
    SqlEdit,
    Tablespaces,
};
use PHPUnit\Framework\Attributes\{CoversMethod, DataProvider};
use PHPUnit\Framework\TestCase;

#[CoversMethod(Website::class, 'buildHtmlDocument')]
#[CoversMethod(Website::class, 'requiresServerLogin')]
#[CoversMethod(Browser::class, 'requiresServerLogin')]
#[CoversMethod(Index::class, 'requiresServerLogin')]
#[CoversMethod(Intro::class, 'requiresServerLogin')]
#[CoversMethod(Login::class, 'requiresServerLogin')]
#[CoversMethod(Redirect::class, 'requiresServerLogin')]
#[CoversMethod(ServerLogout::class, 'requiresServerLogin')]
#[CoversMethod(Servers::class, 'requiresServerLogin')]
final class WebsiteTest extends TestCase
{
    public function testDocument(): void
    {
        $dom = self::page()->buildHtmlDocument();

        self::assertSame('html', $dom->doctype?->name);

        $html = $dom->documentElement;
        self::assertInstanceOf(\DOMElement::class, $html);
        self::assertSame('html', $html->tagName);
        // The locale en_US of the test configuration, as a language tag.
        self::assertSame('en-US', $html->getAttribute('lang'));
        self::assertSame(['head', 'body'], self::childElementNames($html));
    }

    public function testAPageReloadsTheBrowserTreeWhenAnEarlierRequestAskedForIt(): void
    {
        Session::requestBrowserReload();

        $body = self::body(self::page()->buildHtmlDocument());

        // At the end of the page, like the legacy Misc::printFooter().
        self::assertSame(['p', 'script'], self::childElementNames($body));
        self::assertSame('parent.frames.browser.location.reload();', $body->lastElementChild?->textContent);
    }

    public function testOnlyTheNextPageReloadsTheBrowserTree(): void
    {
        Session::requestBrowserReload();
        self::page()->buildHtmlDocument();

        self::assertSame(['p'], self::childElementNames(self::body(self::page()->buildHtmlDocument())));
    }

    public function testWithoutARequestAPageDoesNotReloadTheBrowserTree(): void
    {
        self::assertSame(['p'], self::childElementNames(self::body(self::page()->buildHtmlDocument())));
    }

    /**
     * @param class-string<Website> $page
     */
    #[DataProvider('pagesOfAServer')]
    public function testAPageOfAServerIsOnlyShownWhileOneIsLoggedInToTheServer(string $page): void
    {
        self::assertTrue($page::requiresServerLogin());
    }

    /**
     * @param class-string<Website> $page
     */
    #[DataProvider('pagesWithoutAServer')]
    public function testAPageWithoutAServerIsShownWithoutALogin(string $page): void
    {
        self::assertFalse($page::requiresServerLogin());
    }

    /**
     * Like the legacy pages, which need a login unless they set $_no_db_connection (see libraries/lib.inc.php).
     *
     * @return iterable<string, array{class-string<Website>}>
     */
    public static function pagesOfAServer(): iterable
    {
        $pages = [
            AllDb::class,
            AllDbExport::class,
            AlterDb::class,
            AlterRole::class,
            AlterTablespace::class,
            CreateDb::class,
            CreateRole::class,
            CreateTablespace::class,
            DbExport::class,
            DropDb::class,
            DropRole::class,
            DropTablespace::class,
            History::class,
            HistoryClear::class,
            HistoryDelete::class,
            HistoryDownload::class,
            Languages::class,
            Roles::class,
            SqlEdit::class,
            Tablespaces::class,
        ];

        foreach ($pages as $page) {
            yield $page => [$page];
        }
    }

    /**
     * Like the legacy pages index.php, intro.php, browser.php, servers.php (also for the logout) and redirect.php,
     * which set $_no_db_connection, and the login form, which is shown while one is not logged in.
     *
     * @return iterable<string, array{class-string<Website>}>
     */
    public static function pagesWithoutAServer(): iterable
    {
        $pages = [
            Browser::class,
            Index::class,
            Intro::class,
            Login::class,
            Redirect::class,
            ServerLogout::class,
            Servers::class,
        ];

        foreach ($pages as $page) {
            yield $page => [$page];
        }
    }

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * A page with one paragraph as its content.
     */
    private static function page(): Website
    {
        return new class extends Website {
            public function __construct()
            {
                // Skips the constructor of Website, which starts a PHP session: the tests set $_SESSION themselves.
            }

            protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
            {
                $body = parent::buildHtmlBody($dom);
                $body->appendChild($dom->createElement('p', 'Content'));

                return $body;
            }
        };
    }

    private static function body(\DOMDocument $dom): \DOMElement
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        self::assertInstanceOf(\DOMElement::class, $body);

        return $body;
    }

    /**
     * @return list<string>
     */
    private static function childElementNames(\DOMElement $element): array
    {
        $names = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $names[] = $child->tagName;
            }
        }

        return $names;
    }
}
