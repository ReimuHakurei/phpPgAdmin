<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class WebsiteTest
{
    #[TestRule]
    public function testWebsiteClassesArePages(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('PhpPgAdmin\Website'))
            ->should()->extend()
            ->classes(Selector::classname('PhpPgAdmin\Website'))
            ->because('The namespace holds the pages only; what they share belongs to PhpPgAdmin\WebsiteComponents.');
    }
}
