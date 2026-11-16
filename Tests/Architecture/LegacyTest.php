<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * The legacy classes live in the global namespace. PHPStan knows ADOdb through Composer, but the classes of phpPgAdmin
 * itself only through scanDirectories in phpstan.neon.dist: a class it does not know is not matched by any selector.
 */
final class LegacyTest
{
    #[TestRule]
    public function testSrcDoesNotDependOnLegacyClasses(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('PhpPgAdmin'))
            ->shouldNot()->dependOn()
            ->classes(Selector::classname('/^[^\\\\]+$/', true))
            ->because('src/ replaces the legacy code, so it must not build on it.');
    }
}
