<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\Infrastructure\Http\RequestParameter;
use PhpPgAdmin\Infrastructure\Session\History;
use PhpPgAdmin\Website;

final class HistoryClear extends Website
{
    public function __construct()
    {
        parent::__construct();

        $requestMethod = 'GET';

        if (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])) {
            $requestMethod = strtoupper($_SERVER['REQUEST_METHOD']);
        }

        if ($requestMethod !== 'POST') {
            return;
        }

        $serverId = RequestParameter::getString('server') ?? '';
        $selectedDatabase = RequestParameter::getString('database') ?? '';
        $yes = RequestParameter::getString('yes');

        if (!is_null($yes)) {
            History::clear($serverId, $selectedDatabase);
        }

        if (headers_sent()) {
            return;
        }

        $redirectUrl = 'history.php';
        $redirectUrlParams = [
            'database' => $selectedDatabase,
            'server' => $serverId,
            'subject' => 'table',
        ];
        header('Location: ' . $redirectUrl . '?' . http_build_query($redirectUrlParams));
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);

        $serverId = RequestParameter::getString('server') ?? '';
        $selectedDatabase = RequestParameter::getString('database') ?? '';

        $h3 = $dom->createElement('h3');
        $h3->appendChild($dom->createTextNode(_('Clear history')));
        $body->appendChild($h3);

        $p = $dom->createElement('p');
        $p->appendChild($dom->createTextNode(_('Really clear history?')));
        $body->appendChild($p);

        $form = $dom->createElement('form');
        $form->setAttribute('method', 'post');
        $inputServer = $dom->createElement('input');
        $inputServer->setAttribute('type', 'hidden');
        $inputServer->setAttribute('name', 'server');
        $inputServer->setAttribute('value', $serverId);
        $inputDatabase = $dom->createElement('input');
        $inputDatabase->setAttribute('type', 'hidden');
        $inputDatabase->setAttribute('name', 'database');
        $inputDatabase->setAttribute('value', $selectedDatabase);
        $inputYes = $dom->createElement('input');
        $inputYes->setAttribute('type', 'submit');
        $inputYes->setAttribute('name', 'yes');
        $inputYes->setAttribute('value', _('Yes'));
        $inputNo = $dom->createElement('input');
        $inputNo->setAttribute('type', 'submit');
        $inputNo->setAttribute('name', 'no');
        $inputNo->setAttribute('value', _('No'));
        $form->appendChild($inputServer);
        $form->appendChild($inputDatabase);
        $form->appendChild($inputYes);
        $form->appendChild($dom->createTextNode(' '));
        $form->appendChild($inputNo);
        $body->appendChild($form);

        return $body;
    }
}
