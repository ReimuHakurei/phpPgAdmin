<?php

declare(strict_types=1);

use PhpPgAdmin\Api\Languages\Tree;
use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Website\Languages;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

// The browser tree loads the Languages node of a database from here (see the legacy database.php?action=tree).
if (RequestParameter::getString('action') === 'tree') {
    $tree = new Tree();
    $tree->outputXmlTree();

    exit;
}

$website = new Languages();
echo $website->buildHtmlString();
