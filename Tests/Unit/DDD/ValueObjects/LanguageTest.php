<?php

declare(strict_types=1);

namespace Tests\Unit\DDD\ValueObjects;

use PhpPgAdmin\DDD\ValueObjects\Language;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Language::class)]
final class LanguageTest extends TestCase
{
    public function testProperties(): void
    {
        $language = new Language(name: 'plpgsql', isTrusted: true, callHandler: 'plpgsql_call_handler');

        self::assertSame('plpgsql', $language->Name);
        self::assertTrue($language->IsTrusted);
        self::assertSame('plpgsql_call_handler', $language->CallHandler);
    }

    public function testDefaults(): void
    {
        $language = new Language(name: 'internal');

        self::assertFalse($language->IsTrusted);
        // pg_language stores no call handler for the built-in languages, which regproc shows as "-".
        self::assertSame('-', $language->CallHandler);
    }

    #[DataProvider('emptyNames')]
    public function testEmptyNameThrowsException(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $language = new Language(name: $name);

        self::fail("Expected an exception for an empty name, got the language {$language->Name}.");
    }

    public function testUnknownPropertyThrowsException(): void
    {
        $language = new Language(name: 'plpgsql');

        $this->expectException(\InvalidArgumentException::class);

        $language->__get('Owner');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emptyNames(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => [' '];
    }
}
