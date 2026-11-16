<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpPgAdmin\Session;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Session::class)]
final class SessionTest extends TestCase
{
    public function testWithoutARequestTheBrowserTreeIsNotReloaded(): void
    {
        self::assertFalse(Session::consumeBrowserReloadRequest());
    }

    public function testARequestedBrowserReloadIsConsumedOnce(): void
    {
        Session::requestBrowserReload();

        self::assertTrue(Session::consumeBrowserReloadRequest());
        self::assertFalse(Session::consumeBrowserReloadRequest(), 'Only the next page reloads the browser tree.');
    }

    public function testAConsumedBrowserReloadRequestLeavesTheRestOfTheSessionAlone(): void
    {
        $_SESSION['webdbLogin'] = ['127.0.0.1:5432:allow' => ['username' => 'postgres']];

        Session::requestBrowserReload();
        Session::consumeBrowserReloadRequest();

        self::assertSame(['webdbLogin' => ['127.0.0.1:5432:allow' => ['username' => 'postgres']]], $_SESSION);
    }

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }
}
