<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTO;

use PhpPgAdmin\Application\DTO\Language as DTOLanguage;
use PhpPgAdmin\DDD\ValueObjects\Language;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DTOLanguage::class)]
final class LanguageTest extends TestCase
{
    public function testCreateFromDbArray(): void
    {
        $language = DTOLanguage::createFromDbArray([
            'lanname' => 'plpgsql',
            'lanplcallf' => 'plpgsql_call_handler',
            'lanpltrusted' => true,
        ]);

        self::assertInstanceOf(Language::class, $language);
        self::assertSame('plpgsql', $language->Name);
        self::assertTrue($language->IsTrusted);
        self::assertSame('plpgsql_call_handler', $language->CallHandler);
    }

    public function testCreateFromDbArrayWithAnUntrustedLanguage(): void
    {
        $language = DTOLanguage::createFromDbArray([
            'lanname' => 'c',
            'lanplcallf' => '-',
            'lanpltrusted' => false,
        ]);

        self::assertFalse($language->IsTrusted);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidDbArrays')]
    public function testCreateFromDbArrayRejectsInvalidData(array $data, string $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        DTOLanguage::createFromDbArray($data);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidDbArrays(): iterable
    {
        $valid = [
            'lanname' => 'plpgsql',
            'lanplcallf' => 'plpgsql_call_handler',
            'lanpltrusted' => true,
        ];

        foreach (array_keys($valid) as $field) {
            $data = $valid;
            unset($data[$field]);

            yield "missing {$field}" => [$data, "Missing field '{$field}'"];
        }

        yield 'lanname not a string' => [
            array_merge($valid, ['lanname' => 1]),
            "Type of field 'lanname' is not string.",
        ];
        yield 'lanpltrusted not a bool' => [
            array_merge($valid, ['lanpltrusted' => 't']),
            "Type of field 'lanpltrusted' is not bool.",
        ];
        yield 'lanplcallf not a string' => [
            array_merge($valid, ['lanplcallf' => 1]),
            "Type of field 'lanplcallf' is not string.",
        ];
    }
}
