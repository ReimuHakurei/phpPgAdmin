<?php

declare(strict_types=1);

namespace PhpPgAdmin;

enum TrailSubject: string
{
    case Database = 'database';
    case Role = 'role';
    case Server = 'server';
    case Tablespace = 'tablespace';
}
