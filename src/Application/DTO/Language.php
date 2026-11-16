<?php

declare(strict_types=1);

namespace PhpPgAdmin\Application\DTO;

use PhpPgAdmin\DDD\ValueObjects\Language as ValueObjectLanguage;

abstract readonly class Language
{
    /**
     * @param array<mixed> $data A row of pg_language with lanplcallf as regproc.
     */
    public static function createFromDbArray(array $data): ValueObjectLanguage
    {
        $requiredFields = [
            'lanname',
            'lanpltrusted',
            'lanplcallf',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing field '$field' in data for Language value object.");
            }
        }

        if (!is_string($data['lanname'])) {
            throw new \InvalidArgumentException("Type of field 'lanname' is not string.");
        }

        if (!is_bool($data['lanpltrusted'])) {
            throw new \InvalidArgumentException("Type of field 'lanpltrusted' is not bool.");
        }

        if (!is_string($data['lanplcallf'])) {
            throw new \InvalidArgumentException("Type of field 'lanplcallf' is not string.");
        }

        return new ValueObjectLanguage(
            name: $data['lanname'],
            isTrusted: $data['lanpltrusted'],
            callHandler: $data['lanplcallf'],
        );
    }
}
