<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class ExceptionsTest
{
    #[TestRule]
    public function testExceptionsAreFinal(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::AllOf(
                    Selector::inNamespace('PhpPgAdmin'),
                    Selector::isException(),
                ),
            )
            ->should()->beFinal()
            ->because('Exceptions are no base classes: every error case gets an exception of its own.');
    }

    #[TestRule]
    public function testExceptionNamesEndWithException(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::AllOf(
                    Selector::inNamespace('PhpPgAdmin'),
                    Selector::isException(),
                ),
            )
            ->should()->beNamed('/Exception$/', true)
            ->because('The name tells at the throw and at the catch that the class is an exception.');
    }
}
