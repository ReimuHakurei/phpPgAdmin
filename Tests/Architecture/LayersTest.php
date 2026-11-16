<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * The domain (PhpPgAdmin\DDD) depends on nothing but itself, which DDD\DomainTest checks.
 *
 * The base classes PhpPgAdmin\Website and PhpPgAdmin\WebsiteComponents live in the namespace PhpPgAdmin, so the rules
 * name them by class: Selector::inNamespace() only matches the classes below them.
 */
final class LayersTest
{
    #[TestRule]
    public function testApplicationDoesNotDependOnPresentationOrDatabase(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('PhpPgAdmin\Application'))
            ->shouldNot()->dependOn()
            ->classes(
                Selector::classname('PhpPgAdmin\Website'),
                Selector::inNamespace('PhpPgAdmin\Website'),
                Selector::classname('PhpPgAdmin\WebsiteComponents'),
                Selector::inNamespace('PhpPgAdmin\WebsiteComponents'),
                Selector::inNamespace('PhpPgAdmin\Api'),
                Selector::inNamespace('PhpPgAdmin\Database'),
            )
            ->because('The pages, the API and the database access use the application layer, not the other way round.');
    }

    #[TestRule]
    public function testApiDoesNotDependOnWebsite(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('PhpPgAdmin\Api'))
            ->shouldNot()->dependOn()
            ->classes(
                Selector::classname('PhpPgAdmin\Website'),
                Selector::inNamespace('PhpPgAdmin\Website'),
                Selector::classname('PhpPgAdmin\WebsiteComponents'),
                Selector::inNamespace('PhpPgAdmin\WebsiteComponents'),
            )
            ->because('The API answers with data, not with HTML of the pages.');
    }
}
