<?php

declare(strict_types=1);

namespace Tests\Unit\WebsiteComponents;

use PhpPgAdmin\TrailSubject;
use PhpPgAdmin\WebsiteComponents\TrailBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The request parameters (server, database, …) are not available on the command line, so they are all empty here.
 * The labels and the URL parameters taken from them are covered by the E2E tests.
 */
#[CoversClass(TrailBuilder::class)]
final class TrailBuilderTest extends TestCase
{
    /**
     * @param ?array<string, string> $urlParams Null for a crumb without a link target.
     */
    #[DataProvider('subjects')]
    public function testCrumb(
        TrailSubject $subject,
        string $title,
        string $icon,
        string $help,
        ?string $urlPath,
        ?array $urlParams,
    ): void {
        $dom = new \DOMDocument();
        $crumb = TrailBuilder::buildTrailFor($subject, $dom);
        $dom->appendChild($crumb);

        self::assertSame('td', $crumb->tagName);
        self::assertSame('crumb', $crumb->getAttribute('class'));

        $links = $crumb->getElementsByTagName('a');
        self::assertCount(2, $links);

        $link = $links->item(0);
        self::assertInstanceOf(\DOMElement::class, $link);
        self::assertSame($title, $link->getAttribute('title'));

        if (is_null($urlPath)) {
            self::assertFalse($link->hasAttribute('href'));
        } else {
            self::assertSame(['params' => $urlParams, 'path' => $urlPath], self::parseUrl($link->getAttribute('href')));
        }

        $spans = $link->getElementsByTagName('span');
        self::assertCount(2, $spans);

        $iconSpan = $spans->item(0);
        self::assertInstanceOf(\DOMElement::class, $iconSpan);
        self::assertSame('icon', $iconSpan->getAttribute('class'));
        self::assertSame('label', $spans->item(1)?->getAttribute('class'));

        $img = $iconSpan->getElementsByTagName('img')->item(0);
        self::assertInstanceOf(\DOMElement::class, $img);
        self::assertSame("images/themes/default/{$icon}.png", $img->getAttribute('src'));
        self::assertSame($title, $img->getAttribute('alt'));

        $helpLink = $links->item(1);
        self::assertInstanceOf(\DOMElement::class, $helpLink);
        self::assertSame('help', $helpLink->getAttribute('class'));
        self::assertSame('Help', $helpLink->getAttribute('title'));
        self::assertSame('phppgadminhelp', $helpLink->getAttribute('target'));
        self::assertSame(
            ['params' => ['help' => $help, 'server' => ''], 'path' => 'help.php'],
            self::parseUrl($helpLink->getAttribute('href')),
        );

        self::assertSame(': ', $crumb->lastChild?->textContent);
    }

    /**
     * @return iterable<string, array{TrailSubject, string, string, string, ?string, ?array<string, string>}>
     */
    public static function subjects(): iterable
    {
        yield 'database' => [
            TrailSubject::Database,
            'Database',
            'Database',
            'pg.database',
            // Like the legacy Misc::getTrail(): redirect.php opens the database tab that was used last.
            'redirect.php',
            ['database' => '', 'server' => '', 'subject' => 'database'],
        ];
        yield 'role' => [
            TrailSubject::Role,
            'Role',
            'Roles',
            'pg.role',
            'roles.php',
            ['action' => 'properties', 'rolename' => '', 'server' => ''],
        ];
        yield 'server' => [
            TrailSubject::Server,
            'Server',
            'Servers',
            'pg.server',
            'all_db.php',
            ['server' => ''],
        ];
        yield 'tablespace' => [
            TrailSubject::Tablespace,
            'Tablespace',
            'Tablespaces',
            'pg.tablespace',
            null,
            null,
        ];
    }

    /**
     * @return array{'params': array<mixed>, 'path': string}
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
}
