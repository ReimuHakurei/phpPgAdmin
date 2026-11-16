<?php

declare(strict_types=1);

namespace Tests\Unit\Website;

use PhpPgAdmin\DDD\ValueObjects\Language;
use PhpPgAdmin\Website\Languages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Languages::class)]
final class LanguagesTest extends TestCase
{
    public function testTheTableHasAColumnPerProperty(): void
    {
        $table = self::languageList([new Language(name: 'plpgsql')]);

        self::assertSame('table', $table->tagName);
        self::assertSame([['Name', 'Trusted', 'Function']], self::cellTexts($table, 'thead'));

        foreach ($table->getElementsByTagName('th') as $th) {
            self::assertSame('data', $th->getAttribute('class'));
        }
    }

    public function testOneRowPerLanguage(): void
    {
        $table = self::languageList([
            new Language(name: 'plpgsql', isTrusted: true, callHandler: 'plpgsql_call_handler'),
            new Language(name: 'plperlu', isTrusted: false, callHandler: 'plperlu_call_handler'),
        ]);

        self::assertSame(
            [
                ['plpgsql', 'Yes', 'plpgsql_call_handler'],
                ['plperlu', 'No', 'plperlu_call_handler'],
            ],
            self::cellTexts($table, 'tbody'),
        );
    }

    public function testTheRowsAlternate(): void
    {
        $table = self::languageList([
            new Language(name: 'plperl'),
            new Language(name: 'plpgsql'),
            new Language(name: 'plpython3u'),
        ]);

        $classes = [];

        foreach (self::rows($table, 'tbody') as $tr) {
            $classes[] = $tr->getAttribute('class');
        }

        // Like the tables of the legacy pages (Misc::printTable()).
        self::assertSame(['data1', 'data2', 'data1'], $classes);
    }

    public function testLanguageNamesAreEscaped(): void
    {
        $dom = new \DOMDocument();
        $table = Languages::buildLanguageList($dom, [new Language(name: 'pl<&>')]);

        self::assertSame([['pl<&>', 'No', '-']], self::cellTexts($table, 'tbody'));
        self::assertStringContainsString('<td>pl&lt;&amp;&gt;</td>', (string)$dom->saveHTML($table));
    }

    public function testWithoutLanguagesThePageSaysSo(): void
    {
        $element = self::languageList([]);

        self::assertSame('p', $element->tagName);
        self::assertSame('No languages found.', $element->textContent);
    }

    public function testWithoutADatabaseThePageIsABadRequest(): void
    {
        // Without its constructor, which starts a PHP session and needs a login. The tests have no request, so the
        // database parameter is missing. Only the body, which checks the parameters: buildHtmlDocument() would also
        // run the head of Website, which this test does not check.
        $page = (new \ReflectionClass(Languages::class))->newInstanceWithoutConstructor();
        $buildHtmlBody = new \ReflectionMethod($page, 'buildHtmlBody');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Missing required parameter: database');

        $buildHtmlBody->invoke($page, new \DOMDocument());
    }

    /**
     * @param array<Language> $languages
     */
    private static function languageList(array $languages): \DOMElement
    {
        return Languages::buildLanguageList(new \DOMDocument(), $languages);
    }

    /**
     * @return list<\DOMElement>
     */
    private static function rows(\DOMElement $table, string $section): array
    {
        $sections = $table->getElementsByTagName($section);
        self::assertCount(1, $sections);
        $sectionElement = $sections->item(0);
        self::assertInstanceOf(\DOMElement::class, $sectionElement);

        $rows = [];

        foreach ($sectionElement->childNodes as $child) {
            self::assertInstanceOf(\DOMElement::class, $child);
            self::assertSame('tr', $child->tagName);
            $rows[] = $child;
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private static function cellTexts(\DOMElement $table, string $section): array
    {
        $texts = [];

        foreach (self::rows($table, $section) as $tr) {
            $rowTexts = [];

            foreach ($tr->childNodes as $cell) {
                self::assertInstanceOf(\DOMElement::class, $cell);
                $rowTexts[] = $cell->textContent;
            }

            $texts[] = $rowTexts;
        }

        return $texts;
    }
}
