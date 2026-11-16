<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Languages;

use PhpPgAdmin\Api\Languages\Tree;
use PhpPgAdmin\DDD\ValueObjects\Language;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tree::class)]
final class TreeTest extends TestCase
{
    public function testOneLeafPerLanguage(): void
    {
        $dom = Tree::buildXmlTree([
            new Language(name: 'plpgsql', isTrusted: true, callHandler: 'plpgsql_call_handler'),
            new Language(name: 'plperl', isTrusted: true, callHandler: 'plperl_call_handler'),
        ]);

        self::assertSame(
            [
                [
                    'icon' => 'images/themes/default/Language.png',
                    'openicon' => 'images/themes/default/Language.png',
                    'text' => 'plpgsql',
                ],
                [
                    'icon' => 'images/themes/default/Language.png',
                    'openicon' => 'images/themes/default/Language.png',
                    'text' => 'plperl',
                ],
            ],
            self::leaves($dom),
        );
    }

    public function testWithoutLanguagesTheTreeSaysSo(): void
    {
        $dom = Tree::buildXmlTree([]);

        // Same node as the legacy Misc::printTreeXML(): clicking it reloads the parent node.
        self::assertSame(
            [
                [
                    'icon' => 'images/themes/default/ObjectNotFound.png',
                    'onaction' => 'tree.getSelected().getParent().reload()',
                    'text' => 'No objects found.',
                ],
            ],
            self::leaves($dom),
        );
    }

    public function testDocument(): void
    {
        $dom = Tree::buildXmlTree([new Language(name: 'plpgsql')]);

        self::assertSame('tree', $dom->documentElement?->tagName);
        self::assertStringStartsWith('<?xml version="1.0" encoding="utf-8"?>', (string)$dom->saveXML());
    }

    public function testLanguageNamesAreEscaped(): void
    {
        $dom = Tree::buildXmlTree([new Language(name: 'pl<"&>')]);

        self::assertStringContainsString('text="pl&lt;&quot;&amp;&gt;"', (string)$dom->saveXML());
        self::assertSame('pl<"&>', self::leaves($dom)[0]['text'] ?? null);
    }

    /**
     * The attributes of the child nodes of the root node, sorted by name.
     *
     * @return array<array<string, string>>
     */
    private static function leaves(\DOMDocument $dom): array
    {
        $root = $dom->documentElement;
        self::assertInstanceOf(\DOMElement::class, $root);

        $leaves = [];

        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            self::assertSame('tree', $child->tagName);
            self::assertFalse($child->hasChildNodes(), 'Languages have no child nodes.');

            $attributes = [];

            foreach ($child->attributes as $attribute) {
                $attributes[$attribute->name] = $attribute->value;
            }

            ksort($attributes);
            $leaves[] = $attributes;
        }

        return $leaves;
    }
}
