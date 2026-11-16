<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestParameter::class)]
final class RequestParameterTest extends TestCase
{
    public function testGetStringReturnsTheValueOfTheQuery(): void
    {
        $_GET['server'] = '127.0.0.1:5432:allow';

        self::assertSame('127.0.0.1:5432:allow', RequestParameter::getString('server'));
    }

    public function testGetStringReturnsTheValueOfThePostedForm(): void
    {
        $_POST['server'] = '127.0.0.1:5432:allow';

        self::assertSame('127.0.0.1:5432:allow', RequestParameter::getString('server'));
    }

    public function testGetStringPrefersTheQueryToThePostedForm(): void
    {
        $_GET['server'] = 'query';
        $_POST['server'] = 'form';

        self::assertSame('query', RequestParameter::getString('server'));
    }

    public function testGetStringSkipsAnArray(): void
    {
        $_GET['server'] = ['query'];
        $_POST['server'] = 'form';

        self::assertSame('form', RequestParameter::getString('server'));
    }

    public function testGetStringWithoutTheParameterReturnsTheDefault(): void
    {
        self::assertNull(RequestParameter::getString('server'));
        self::assertSame('default', RequestParameter::getString('server', 'default'));
    }

    public function testGetArrayReturnsTheValueOfTheQuery(): void
    {
        $_GET['ids'] = ['1', '2'];

        self::assertSame(['1', '2'], RequestParameter::getArray('ids'));
    }

    public function testGetArrayReturnsTheValueOfThePostedForm(): void
    {
        $_POST['ids'] = ['1', '2'];

        self::assertSame(['1', '2'], RequestParameter::getArray('ids'));
    }

    public function testGetArrayPrefersTheQueryToThePostedForm(): void
    {
        $_GET['ids'] = ['query'];
        $_POST['ids'] = ['form'];

        self::assertSame(['query'], RequestParameter::getArray('ids'));
    }

    public function testGetArraySkipsAString(): void
    {
        $_GET['ids'] = 'query';
        $_POST['ids'] = ['form'];

        self::assertSame(['form'], RequestParameter::getArray('ids'));
    }

    public function testGetArrayWithoutTheParameterReturnsTheDefault(): void
    {
        self::assertNull(RequestParameter::getArray('ids'));
        self::assertSame(['default'], RequestParameter::getArray('ids', ['default']));
    }

    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }
}
