<?php

declare(strict_types=1);

namespace PhpPgAdmin\Application\DTO;

use PhpPgAdmin\Config;
use PhpPgAdmin\DDD\Entities\ServerSession as EntityServerSession;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\Logins;

abstract readonly class ServerSession
{
    public static function createFromRequestParameter(): ?EntityServerSession
    {
        $serverId = RequestParameter::getString('server');

        if (is_null($serverId)) {
            return null;
        }

        return Logins::serverSession($serverId, Config::getServers());
    }
}
