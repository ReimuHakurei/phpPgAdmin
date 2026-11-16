<?php

declare(strict_types=1);

namespace PhpPgAdmin\DDD\ValueObjects;

/**
 * A language functions can be written in (pg_language), not the language of the user interface.
 *
 * @property-read string $CallHandler
 * @property-read bool $IsTrusted
 * @property-read string $Name
 */
final readonly class Language
{
    /**
     * @param string $callHandler "-" for the built-in languages, which have no call handler.
     */
    public function __construct(
        private string $name,
        private bool $isTrusted = false,
        private string $callHandler = '-',
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Language name cannot be empty!');
        }
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'CallHandler' => $this->callHandler,
            'IsTrusted' => $this->isTrusted,
            'Name' => $this->name,
            default => throw new \InvalidArgumentException("Property '$name' does not exist."),
        };
    }
}
