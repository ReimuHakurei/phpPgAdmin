<?php

declare(strict_types=1);

namespace PhpPgAdmin\Infrastructure\Http;

/**
 * The parameters of the request, from the posted form before the query, as PHP fills $_REQUEST by default.
 *
 * Reads $_GET and $_POST rather than filter_input(), which only knows the request PHP started with and ignores what
 * tests write into them.
 */
final class RequestParameter
{
    /**
     * @param ?array<mixed> $default
     * @return ?array<mixed>
     */
    public static function getArray(string $name, ?array $default = null): ?array
    {
        $value = $_POST[$name] ?? null;

        if (!is_array($value)) {
            $value = $_GET[$name] ?? null;
        }

        if (!is_array($value)) {
            $value = $default;
        }

        return $value;
    }

    public static function getString(string $name, ?string $default = null): ?string
    {
        $value = $_POST[$name] ?? null;

        if (!is_string($value)) {
            $value = $_GET[$name] ?? null;
        }

        if (!is_string($value)) {
            $value = $default;
        }

        return $value;
    }
}
