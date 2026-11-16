<?php

declare(strict_types=1);

namespace Tests\Architecture\DDD;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class ValueObjectsTest
{
    #[TestRule]
    public function testValueObjectsAreReadonly(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::AllOf(
                    Selector::inNamespace('PhpPgAdmin\DDD\ValueObjects'),
                    Selector::Not(Selector::isEnum()),
                ),
            )
            ->should()->beReadonly()
            ->because('ValueObjects are immutable by definition.');
    }

    #[TestRule]
    public function testValueObjectsAreFinal(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::AllOf(
                    Selector::inNamespace('PhpPgAdmin\DDD\ValueObjects'),
                    Selector::Not(Selector::isEnum()),
                ),
            )
            ->should()->beFinal()
            ->because('A subclass could override methods, and two equal values would no longer behave the same.');
    }
}
