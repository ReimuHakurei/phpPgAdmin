<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpPgAdmin\{Website, WebsiteComponents};
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestEnvironment;

#[CoversClass(WebsiteComponents::class)]
final class WebsiteComponentsTest extends TestCase
{
    private const DATABASE = 'my "db"';
    private const SERVER_ID = '127.0.0.1:5432:allow';

    /**
     * A server of the test configuration, which the top bar needs. Not the running server, whose host depends on the
     * environment.
     */
    private const TOP_BAR_PLATFORM = 'PostgreSQL 17.0';
    private const TOP_BAR_SERVER_ID = TestEnvironment::NOT_RUNNING_SERVER_HOST . ':'
        . TestEnvironment::NOT_RUNNING_SERVER_PORT . ':allow';

    /**
     * The windows the links of the top bar open in, named after the server like in the legacy Misc::printTopbar().
     * The SQL and the Find link share one.
     */
    private const HISTORY_WINDOW = 'history:' . self::TOP_BAR_SERVER_ID;
    private const SQL_WINDOW = 'sqledit:' . self::TOP_BAR_SERVER_ID;

    /**
     * Id, label, icon, URL path, additional URL parameters and help page of the tabs, in the order of the legacy
     * Misc::getNavTabs('database'). A list, because phpcbf sorts the keys of keyed arrays.
     */
    private const DATABASE_TABS = [
        ['schemas', 'Schemas', 'Schemas', 'schemas.php', [], 'pg.schema'],
        ['sql', 'SQL', 'SqlEditor', 'database.php', ['action' => 'sql', 'new' => '1'], 'pg.sql'],
        ['find', 'Find', 'Search', 'database.php', ['action' => 'find'], null],
        ['variables', 'Variables', 'Variables', 'database.php', ['action' => 'variables'], 'pg.variable'],
        ['processes', 'Processes', 'Processes', 'database.php', ['action' => 'processes'], 'pg.process'],
        ['locks', 'Locks', 'Key', 'database.php', ['action' => 'locks'], 'pg.locks'],
        ['admin', 'Admin', 'Admin', 'database.php', ['action' => 'admin'], null],
        ['privileges', 'Privileges', 'Privileges', 'privileges.php', [], 'pg.privilege'],
        ['languages', 'Languages', 'Languages', 'languages.php', [], 'pg.language'],
        ['casts', 'Casts', 'Casts', 'casts.php', [], 'pg.cast'],
        ['export', 'Export', 'Export', 'database.php', ['action' => 'export'], null],
    ];

    /**
     * Only shown with show_advanced, as in the legacy Misc::getNavTabs('database').
     */
    private const ADVANCED_DATABASE_TABS = ['casts', 'languages'];

    /**
     * @var array<mixed>
     */
    private array $request = [];

    public function testDatabaseTabsLabels(): void
    {
        $tabs = self::buildDatabaseTabs(activeTab: 'languages', showAdvanced: true);

        self::assertSame(
            array_column(self::DATABASE_TABS, 1),
            array_map(static fn (\DOMElement $tab): string => self::tabLabel($tab), $tabs),
        );
    }

    public function testDatabaseTabsWithoutShowAdvancedHideTheAdvancedTabs(): void
    {
        $tabs = self::buildDatabaseTabs(activeTab: 'schemas', showAdvanced: false);

        $expectedLabels = [];

        foreach (self::DATABASE_TABS as [$id, $label]) {
            if (!in_array($id, self::ADVANCED_DATABASE_TABS, true)) {
                $expectedLabels[] = $label;
            }
        }

        self::assertSame(
            $expectedLabels,
            array_map(static fn (\DOMElement $tab): string => self::tabLabel($tab), $tabs),
        );
    }

    /**
     * @param array<string, string> $additionalUrlParams
     */
    #[DataProvider('databaseTabs')]
    public function testDatabaseTabLink(
        string $id,
        string $label,
        string $icon,
        string $urlPath,
        array $additionalUrlParams,
    ): void {
        $tab = self::databaseTab($id);
        $link = self::firstElement($tab, 'a');

        $expectedParams = array_merge(
            [
                'database' => self::DATABASE,
                'server' => self::SERVER_ID,
                'subject' => 'database',
            ],
            $additionalUrlParams,
        );
        ksort($expectedParams);

        $url = self::parseUrl($link->getAttribute('href'));
        self::assertSame($urlPath, $url['path']);
        self::assertSame($expectedParams, $url['params']);

        $iconSpans = self::elementsByClass($link, 'span', 'icon');
        self::assertCount(1, $iconSpans);

        $img = self::firstElement($iconSpans[0], 'img');
        self::assertSame("images/themes/default/{$icon}.png", $img->getAttribute('src'));
        self::assertSame($label, $img->getAttribute('alt'));
    }

    #[DataProvider('databaseTabHelpPages')]
    public function testDatabaseTabHelpLink(string $id, ?string $help): void
    {
        $helpLinks = self::elementsByClass(self::databaseTab($id), 'a', 'help');

        if (is_null($help)) {
            self::assertSame([], $helpLinks);

            return;
        }

        self::assertCount(1, $helpLinks);
        self::assertSame('Help', $helpLinks[0]->getAttribute('title'));
        self::assertSame('phppgadminhelp', $helpLinks[0]->getAttribute('target'));
        self::assertSame(
            [
                'params' => [
                    'help' => $help,
                    'server' => self::SERVER_ID,
                ],
                'path' => 'help.php',
            ],
            self::parseUrl($helpLinks[0]->getAttribute('href')),
        );
    }

    public function testDatabaseTabsMarkOnlyTheActiveTab(): void
    {
        $tabs = self::buildDatabaseTabs(activeTab: 'languages', showAdvanced: true);

        $classes = [];

        foreach ($tabs as $tab) {
            $classes[self::tabLabel($tab)] = $tab->getAttribute('class');
        }

        $expectedClasses = array_fill_keys(array_column(self::DATABASE_TABS, 1), 'tab');
        $expectedClasses['Languages'] = 'tab active';

        self::assertSame($expectedClasses, $classes);
    }

    public function testDatabaseTabsShareTheWidth(): void
    {
        $allTabs = self::buildDatabaseTabs(activeTab: 'schemas', showAdvanced: true);
        $basicTabs = self::buildDatabaseTabs(activeTab: 'schemas', showAdvanced: false);

        // 100 / 11 tabs and 100 / 9 tabs, rounded down like the legacy Misc::printTabs().
        self::assertSame(['width: 9%'], array_values(array_unique(self::styles($allTabs))));
        self::assertSame(['width: 11%'], array_values(array_unique(self::styles($basicTabs))));
    }

    public function testDatabaseTabsRememberTheActiveTab(): void
    {
        $_SESSION = [
            'webdbLastTab' => [
                'server' => 'databases',
            ],
        ];

        self::buildDatabaseTabs(activeTab: 'languages', showAdvanced: true);

        // redirect.php?subject=database returns to the last tab, like after the legacy Misc::printTabs().
        $lastTabs = $_SESSION['webdbLastTab'] ?? null;
        self::assertIsArray($lastTabs);
        self::assertCount(2, $lastTabs);
        self::assertSame('languages', $lastTabs['database'] ?? null);
        self::assertSame('databases', $lastTabs['server'] ?? null, 'Other tabs are kept.');
    }

    public function testDatabaseTabsRememberTheActiveTabInANewSession(): void
    {
        $_SESSION = [];

        self::buildDatabaseTabs(activeTab: 'languages', showAdvanced: true);

        self::assertSame(['database' => 'languages'], $_SESSION['webdbLastTab']);
    }

    /**
     * Characterizes the server tabs, which share the rendering with the database tabs.
     */
    public function testServerTabs(): void
    {
        $dom = new \DOMDocument();
        $table = WebsiteComponents::buildServerDatabasesTabs($dom, self::SERVER_ID, 'roles');
        $dom->appendChild($table);

        self::assertSame('tabs', $table->getAttribute('class'));

        $tabs = [];

        foreach (self::elementsByClass($table, 'td', 'tab') as $tab) {
            $link = self::firstElement($tab, 'a');
            $helpLinks = self::elementsByClass($tab, 'a', 'help');

            $tabs[] = [
                'class' => $tab->getAttribute('class'),
                'help' => array_map(
                    static fn (\DOMElement $helpLink): array => self::parseUrl($helpLink->getAttribute('href')),
                    $helpLinks,
                ),
                'icon' => self::firstElement($link, 'img')->getAttribute('src'),
                'label' => self::tabLabel($tab),
                'style' => $tab->getAttribute('style'),
                'url' => self::parseUrl($link->getAttribute('href')),
            ];
        }

        $helpLink = static fn (string $help): array => [
            [
                'params' => [
                    'help' => $help,
                    'server' => self::SERVER_ID,
                ],
                'path' => 'help.php',
            ],
        ];

        self::assertSame(
            [
                [
                    'class' => 'tab',
                    'help' => $helpLink('pg.database'),
                    'icon' => 'images/themes/default/Databases.png',
                    'label' => 'Databases',
                    'style' => 'width: 20%',
                    'url' => [
                        'params' => ['server' => self::SERVER_ID, 'subject' => 'server'],
                        'path' => 'all_db.php',
                    ],
                ],
                [
                    'class' => 'tab active',
                    'help' => $helpLink('pg.role'),
                    'icon' => 'images/themes/default/Roles.png',
                    'label' => 'Roles',
                    'style' => 'width: 20%',
                    'url' => [
                        'params' => ['server' => self::SERVER_ID, 'subject' => 'server'],
                        'path' => 'roles.php',
                    ],
                ],
                [
                    'class' => 'tab',
                    'help' => $helpLink('pg.tablespace'),
                    'icon' => 'images/themes/default/Tablespaces.png',
                    'label' => 'Tablespaces',
                    'style' => 'width: 20%',
                    'url' => [
                        'params' => ['server' => self::SERVER_ID, 'subject' => 'server'],
                        'path' => 'tablespaces.php',
                    ],
                ],
                [
                    'class' => 'tab',
                    'help' => [],
                    'icon' => 'images/themes/default/Export.png',
                    'label' => 'Export',
                    'style' => 'width: 20%',
                    'url' => [
                        'params' => ['server' => self::SERVER_ID],
                        'path' => 'all_db_export.php',
                    ],
                ],
            ],
            $tabs,
        );
    }

    public function testTopBarLinksOfADatabasePageOpenTheDatabase(): void
    {
        // Like the legacy Misc::printTopbar(), whose links take the database of the request (getRequestVars()).
        self::assertSame(
            [
                'toplink_find' => [
                    'params' => [
                        'action' => 'find',
                        'database' => self::DATABASE,
                        'server' => self::TOP_BAR_SERVER_ID,
                        'subject' => 'table',
                    ],
                    'path' => 'sqledit.php',
                    'target' => 'sqledit',
                    'window' => self::SQL_WINDOW,
                ],
                'toplink_history' => [
                    'params' => [
                        'database' => self::DATABASE,
                        'server' => self::TOP_BAR_SERVER_ID,
                        'subject' => 'table',
                    ],
                    'path' => 'history.php',
                    'target' => '',
                    'window' => self::HISTORY_WINDOW,
                ],
                'toplink_logout' => [
                    'params' => ['id' => self::TOP_BAR_SERVER_ID],
                    'path' => 'server-logout.php',
                    'target' => '',
                    'window' => '',
                ],
                'toplink_sql' => [
                    'params' => [
                        'action' => 'sql',
                        'database' => self::DATABASE,
                        'server' => self::TOP_BAR_SERVER_ID,
                        'subject' => 'table',
                    ],
                    'path' => 'sqledit.php',
                    'target' => 'sqledit',
                    'window' => self::SQL_WINDOW,
                ],
            ],
            self::topBarLinks(database: self::DATABASE),
        );
    }

    public function testTopBarLinksOfAServerPageOpenTheServer(): void
    {
        self::assertSame(
            [
                'toplink_find' => [
                    'params' => [
                        'action' => 'find',
                        'server' => self::TOP_BAR_SERVER_ID,
                        'subject' => 'table',
                    ],
                    'path' => 'sqledit.php',
                    'target' => 'sqledit',
                    'window' => self::SQL_WINDOW,
                ],
                'toplink_history' => [
                    'params' => [
                        'server' => self::TOP_BAR_SERVER_ID,
                        'subject' => 'table',
                    ],
                    'path' => 'history.php',
                    'target' => '',
                    'window' => self::HISTORY_WINDOW,
                ],
                'toplink_logout' => [
                    'params' => ['id' => self::TOP_BAR_SERVER_ID],
                    'path' => 'server-logout.php',
                    'target' => '',
                    'window' => '',
                ],
                'toplink_sql' => [
                    'params' => [
                        'action' => 'sql',
                        'server' => self::TOP_BAR_SERVER_ID,
                        'subject' => 'table',
                    ],
                    'path' => 'sqledit.php',
                    'target' => 'sqledit',
                    'window' => self::SQL_WINDOW,
                ],
            ],
            self::topBarLinks(),
        );
    }

    public function testTopBarShowsWhoIsLoggedIn(): void
    {
        $topBar = self::loggedInTopBar();

        // Session security is on in the test configuration.
        self::assertSame([], self::elementsByClass($topBar, 'div', 'alert-banner'));

        $topBarDivs = self::elementsByClass($topBar, 'div', 'topbar');
        self::assertCount(1, $topBarDivs);

        $table = self::firstElement($topBarDivs[0], 'table');
        self::assertSame('width: 100%', $table->getAttribute('style'));

        $cells = $table->getElementsByTagName('td');
        self::assertCount(2, $cells);

        self::assertSame(
            [
                'host' => [TestEnvironment::NOT_RUNNING_SERVER_HOST],
                'platform' => [self::TOP_BAR_PLATFORM],
                'port' => [(string)TestEnvironment::NOT_RUNNING_SERVER_PORT],
                'username' => ['postgres'],
            ],
            self::spanTexts(self::item($cells, 0), ['host', 'platform', 'port', 'username']),
        );
        self::assertSame('text-align: right;', self::item($cells, 1)->getAttribute('style'));
    }

    public function testTopBarLoadsTheScriptThatOpensTheLinksInTheirWindows(): void
    {
        $scripts = self::loggedInTopBar()->getElementsByTagName('script');

        self::assertCount(1, $scripts);
        self::assertSame('js/topbar.js', self::item($scripts, 0)->getAttribute('src'));
        self::assertSame('text/javascript', self::item($scripts, 0)->getAttribute('type'));
    }

    public function testTopBarWithoutALoginShowsTheAppWithoutLinks(): void
    {
        unset($_REQUEST['server']);

        $dom = new \DOMDocument();
        $topBar = WebsiteComponents::buildTopBar($dom);
        $dom->appendChild($topBar);

        $cells = $topBar->getElementsByTagName('td');
        self::assertCount(1, $cells);
        // Like the legacy Misc::printTopbar() without a login.
        self::assertSame(Website::APP_NAME . ' ' . Website::APP_VERSION, self::item($cells, 0)->textContent);
        self::assertSame(
            ['appname' => [Website::APP_NAME], 'version' => [Website::APP_VERSION]],
            self::spanTexts(self::item($cells, 0), ['appname', 'version']),
        );
        self::assertSame([], self::elementsByClass($topBar, 'ul', 'toplink'));
        self::assertCount(0, $topBar->getElementsByTagName('script'));
    }

    public function testBrowserReloadScriptReloadsTheBrowserFrame(): void
    {
        $dom = new \DOMDocument();
        $script = WebsiteComponents::buildBrowserReloadScript($dom);

        self::assertSame('script', $script->tagName);
        self::assertSame('text/javascript', $script->getAttribute('type'));
        // Like the legacy Misc::printReload(); index.php names the iframe of the browser tree "browser".
        self::assertSame('parent.frames.browser.location.reload();', $script->textContent);
    }

    /**
     * @return iterable<string, array{string, string, string, string, array<string, string>}>
     */
    public static function databaseTabs(): iterable
    {
        foreach (self::DATABASE_TABS as [$id, $label, $icon, $urlPath, $additionalUrlParams]) {
            yield $id => [$id, $label, $icon, $urlPath, $additionalUrlParams];
        }
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function databaseTabHelpPages(): iterable
    {
        foreach (self::DATABASE_TABS as [$id, , , , , $help]) {
            yield $id => [$id, $help];
        }
    }

    protected function setUp(): void
    {
        $this->request = $_REQUEST;
    }

    protected function tearDown(): void
    {
        $_REQUEST = $this->request;
        $_SESSION = [];
    }

    /**
     * @return array<\DOMElement>
     */
    private static function buildDatabaseTabs(string $activeTab, bool $showAdvanced): array
    {
        $dom = new \DOMDocument();
        $table = WebsiteComponents::buildDatabaseTabs(
            dom: $dom,
            serverId: self::SERVER_ID,
            database: self::DATABASE,
            activeTab: $activeTab,
            showAdvanced: $showAdvanced,
        );
        $dom->appendChild($table);

        self::assertSame('table', $table->tagName);
        self::assertSame('tabs', $table->getAttribute('class'));

        return self::elementsByClass($table, 'td', 'tab');
    }

    private static function databaseTab(string $id): \DOMElement
    {
        $label = array_column(self::DATABASE_TABS, 1, 0)[$id];

        foreach (self::buildDatabaseTabs(activeTab: 'schemas', showAdvanced: true) as $tab) {
            if (self::tabLabel($tab) === $label) {
                return $tab;
            }
        }

        self::fail("No database tab labelled \"{$label}\".");
    }

    /**
     * Elements whose class list contains $class.
     *
     * @return list<\DOMElement>
     */
    private static function elementsByClass(\DOMElement $context, string $tagName, string $class): array
    {
        $elements = [];

        foreach ($context->getElementsByTagName($tagName) as $element) {
            if (in_array($class, explode(' ', $element->getAttribute('class')), true)) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    private static function firstElement(\DOMElement $context, string $tagName): \DOMElement
    {
        $element = $context->getElementsByTagName($tagName)->item(0);

        self::assertInstanceOf(\DOMElement::class, $element, "No <{$tagName}> found.");

        return $element;
    }

    /**
     * @return array{'path': string, 'params': array<mixed>}
     */
    private static function parseUrl(string $url): array
    {
        $params = [];
        parse_str((string)parse_url($url, PHP_URL_QUERY), $params);
        ksort($params);

        return [
            'params' => $params,
            'path' => (string)parse_url($url, PHP_URL_PATH),
        ];
    }

    /**
     * @param \DOMNodeList<\DOMElement> $elements
     */
    private static function item(\DOMNodeList $elements, int $index): \DOMElement
    {
        $element = $elements->item($index);

        self::assertInstanceOf(\DOMElement::class, $element, "No element {$index} found.");

        return $element;
    }

    /**
     * The top bar while one is logged in to the server of the request.
     */
    private static function loggedInTopBar(?string $database = null): \DOMElement
    {
        $_REQUEST['server'] = self::TOP_BAR_SERVER_ID;
        $_SESSION['webdbLogin'] = [
            self::TOP_BAR_SERVER_ID => [
                'password' => '',
                'platform' => self::TOP_BAR_PLATFORM,
                'username' => 'postgres',
            ],
        ];

        $dom = new \DOMDocument();
        $topBar = WebsiteComponents::buildTopBar($dom, database: $database);
        $dom->appendChild($topBar);

        return $topBar;
    }

    /**
     * The links of the top bar by their id.
     *
     * @return array<string, array{'path': string, 'params': array<mixed>, 'target': string, 'window': string}>
     */
    private static function topBarLinks(?string $database = null): array
    {
        $lists = self::elementsByClass(self::loggedInTopBar($database), 'ul', 'toplink');
        self::assertCount(1, $lists);

        $links = [];

        foreach ($lists[0]->getElementsByTagName('a') as $link) {
            $links[$link->getAttribute('id')] = [
                ...self::parseUrl($link->getAttribute('href')),
                'target' => $link->getAttribute('target'),
                'window' => $link->getAttribute('data-window'),
            ];
        }

        ksort($links);

        return $links;
    }

    /**
     * The texts of the spans with each of the classes.
     *
     * @param list<string> $classes
     * @return array<string, list<string>>
     */
    private static function spanTexts(\DOMElement $context, array $classes): array
    {
        $texts = [];

        foreach ($classes as $class) {
            $texts[$class] = array_map(
                static fn (\DOMElement $span): string => $span->textContent,
                self::elementsByClass($context, 'span', $class),
            );
        }

        return $texts;
    }

    /**
     * @param array<\DOMElement> $tabs
     * @return array<string>
     */
    private static function styles(array $tabs): array
    {
        return array_map(static fn (\DOMElement $tab): string => $tab->getAttribute('style'), $tabs);
    }

    private static function tabLabel(\DOMElement $tab): string
    {
        $labels = self::elementsByClass($tab, 'span', 'label');

        self::assertCount(1, $labels);

        return $labels[0]->textContent;
    }
}
