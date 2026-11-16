<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{TrailSubject, Website, WebsiteComponents};
use PhpPgAdmin\Infrastructure\Http\RequestParameter;

final class AllDbExport extends Website
{
    public function __construct()
    {
        parent::__construct();

        // After the parent constructor, which sets the locale for the translation.
        $this->title = _('Databases');
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);

        $body->appendChild(WebsiteComponents::buildTopBar($dom));
        $body->appendChild(WebsiteComponents::buildTrail($dom, [TrailSubject::Server]));

        $serverId = RequestParameter::getString('server') ?? '';
        $body->appendChild(WebsiteComponents::buildServerDatabasesTabs($dom, $serverId, 'export'));

        $form = $dom->createElement('form');
        $form->setAttribute('action', 'dbexport.php');
        $form->setAttribute('method', 'post');

        $table = $dom->createElement('table');

        $tHead = $dom->createElement('thead');
        $tHeadRow = $dom->createElement('tr');
        $tHeadColumnFormat = $dom->createElement('th', _('Format'));
        $tHeadColumnFormat->setAttribute('class', 'data');
        $tHeadColumnOptions = $dom->createElement('th', _('Options'));
        $tHeadColumnOptions->setAttribute('class', 'data');
        $tHeadRow->appendChild($tHeadColumnFormat);
        $tHeadRow->appendChild($tHeadColumnOptions);
        $tHead->appendChild($tHeadRow);

        $tBody = $dom->createElement('tbody');

        $rowOnlyData = $dom->createElement('tr');
        $rowOnlyDataColumnFormat = $dom->createElement('th');
        $rowOnlyDataColumnFormat->setAttribute('class', 'data left');
        $inputWhat1 = $dom->createElement('input');
        $inputWhat1->setAttribute('type', 'radio');
        $inputWhat1->setAttribute('name', 'what');
        $inputWhat1->setAttribute('id', 'what1');
        $inputWhat1->setAttribute('value', 'dataonly');
        $inputWhat1->setAttribute('checked', 'checked');
        $labelForWhat1 = $dom->createElement('label', _('Data only'));
        $labelForWhat1->setAttribute('for', 'what1');
        $rowOnlyDataColumnFormat->appendChild($inputWhat1);
        $rowOnlyDataColumnFormat->appendChild($labelForWhat1);
        $rowOnlyDataColumnOptions = $dom->createElement('td');
        $rowOnlyDataColumnOptions->appendChild($dom->createTextNode(_('Format')));
        $rowOnlyDataColumnOptions->appendChild($dom->createEntityReference('nbsp'));
        $formatSelect = $dom->createElement('select');
        $formatSelect->setAttribute('name', 'd_format');
        $formatOptions = ['copy', 'sql'];

        foreach ($formatOptions as $option) {
            $optionElement = $dom->createElement('option', strtoupper($option));
            $optionElement->setAttribute('value', $option);
            $formatSelect->appendChild($optionElement);
        }

        $rowOnlyDataColumnOptions->appendChild($formatSelect);
        $rowOnlyData->appendChild($rowOnlyDataColumnFormat);
        $rowOnlyData->appendChild($rowOnlyDataColumnOptions);

        $rowOnlyStructure = $dom->createElement('tr');
        $rowOnlyStructureColumnFormat = $dom->createElement('th');
        $rowOnlyStructureColumnFormat->setAttribute('class', 'data left');
        $inputWhat2 = $dom->createElement('input');
        $inputWhat2->setAttribute('type', 'radio');
        $inputWhat2->setAttribute('name', 'what');
        $inputWhat2->setAttribute('id', 'what2');
        $inputWhat2->setAttribute('value', 'structureonly');
        $inputWhat2->setAttribute('checked', 'checked');
        $labelForWhat2 = $dom->createElement('label', _('Structure only'));
        $labelForWhat2->setAttribute('for', 'what2');
        $rowOnlyStructureColumnFormat->appendChild($inputWhat2);
        $rowOnlyStructureColumnFormat->appendChild($labelForWhat2);
        $rowOnlyStructureColumnOptions = $dom->createElement('td');
        $cleanInputForStructureOnly = $dom->createElement('input');
        $cleanInputForStructureOnly->setAttribute('type', 'checkbox');
        $cleanInputForStructureOnly->setAttribute('id', 's_clean');
        $cleanInputForStructureOnly->setAttribute('name', 's_clean');
        $cleanInputForStructureOnlyLabel = $dom->createElement('label', _('Delete'));
        $cleanInputForStructureOnlyLabel->setAttribute('for', 's_clean');
        $rowOnlyStructureColumnOptions->appendChild($cleanInputForStructureOnly);
        $rowOnlyStructureColumnOptions->appendChild($cleanInputForStructureOnlyLabel);
        $rowOnlyStructure->appendChild($rowOnlyStructureColumnFormat);
        $rowOnlyStructure->appendChild($rowOnlyStructureColumnOptions);

        $rowStructureAndData = $dom->createElement('tr');
        $rowStructureAndDataColumnFormat = $dom->createElement('th');
        $rowStructureAndDataColumnFormat->setAttribute('class', 'data left');
        $inputWhat3 = $dom->createElement('input');
        $inputWhat3->setAttribute('type', 'radio');
        $inputWhat3->setAttribute('name', 'what');
        $inputWhat3->setAttribute('id', 'what3');
        $inputWhat3->setAttribute('value', 'structureanddata');
        $inputWhat3->setAttribute('checked', 'checked');
        $labelForWhat3 = $dom->createElement('label', _('Structure and data'));
        $labelForWhat3->setAttribute('for', 'what3');
        $rowStructureAndDataColumnFormat->appendChild($inputWhat3);
        $rowStructureAndDataColumnFormat->appendChild($labelForWhat3);
        $rowStructureAndDataColumnOptions = $dom->createElement('td');
        $rowStructureAndDataColumnOptions->appendChild($dom->createTextNode(_('Format')));
        $rowStructureAndDataColumnOptions->appendChild($dom->createEntityReference('nbsp'));
        $clonedFormatSelect = $formatSelect->cloneNode(true);

        if ($clonedFormatSelect instanceof \DOMElement) {
            $clonedFormatSelect->setAttribute('name', 'sd_format');
            $rowStructureAndDataColumnOptions->appendChild($clonedFormatSelect);
        }

        $rowStructureAndDataColumnOptions->appendChild($dom->createElement('br'));
        $clonedCleanInputForStructureOnly = $cleanInputForStructureOnly->cloneNode(true);
        $clonedCleanInputForStructureOnlyLabel = $cleanInputForStructureOnlyLabel->cloneNode(true);

        if (
            $clonedCleanInputForStructureOnly instanceof \DOMElement &&
            $clonedCleanInputForStructureOnlyLabel instanceof \DOMElement
        ) {
            $clonedCleanInputForStructureOnly->setAttribute('type', 'checkbox');
            $clonedCleanInputForStructureOnly->setAttribute('id', 'sd_clean');
            $clonedCleanInputForStructureOnly->setAttribute('name', 'sd_clean');
            $clonedCleanInputForStructureOnlyLabel->setAttribute('for', 'sd_clean');
            $rowStructureAndDataColumnOptions->appendChild($clonedCleanInputForStructureOnly);
            $rowStructureAndDataColumnOptions->appendChild($clonedCleanInputForStructureOnlyLabel);
        }

        $rowStructureAndData->appendChild($rowStructureAndDataColumnFormat);
        $rowStructureAndData->appendChild($rowStructureAndDataColumnOptions);

        $tBody->appendChild($rowOnlyData);
        $tBody->appendChild($rowOnlyStructure);
        $tBody->appendChild($rowStructureAndData);

        $table->appendChild($tHead);
        $table->appendChild($tBody);

        $form->appendChild($table);

        $h3Options = $dom->createElement('h3', _('Options'));
        $form->appendChild($h3Options);

        $outputOptions = $dom->createElement('p');
        $inputRadioOutput1 = $dom->createElement('input');
        $inputRadioOutput1->setAttribute('type', 'radio');
        $inputRadioOutput1->setAttribute('id', 'output1');
        $inputRadioOutput1->setAttribute('name', 'output');
        $inputRadioOutput1->setAttribute('value', 'show');
        $inputRadioOutput1->setAttribute('checked', 'checked');
        $labelOutput1 = $dom->createElement('label', _('Show'));
        $labelOutput1->setAttribute('for', 'output1');
        $outputOptions->appendChild($inputRadioOutput1);
        $outputOptions->appendChild($labelOutput1);
        $outputOptions->appendChild($dom->createElement('br'));
        $inputRadioOutput2 = $dom->createElement('input');
        $inputRadioOutput2->setAttribute('type', 'radio');
        $inputRadioOutput2->setAttribute('id', 'output2');
        $inputRadioOutput2->setAttribute('name', 'output');
        $inputRadioOutput2->setAttribute('value', 'download');
        $labelOutput2 = $dom->createElement('label', _('Download'));
        $labelOutput2->setAttribute('for', 'output2');
        $outputOptions->appendChild($inputRadioOutput2);
        $outputOptions->appendChild($labelOutput2);
        $form->appendChild($outputOptions);

        $hiddenInputs = $dom->createElement('p');
        $hiddenInputAction = $dom->createElement('input');
        $hiddenInputAction->setAttribute('type', 'hidden');
        $hiddenInputAction->setAttribute('name', 'action');
        $hiddenInputAction->setAttribute('value', 'export');

        $hiddenInputSubject = $dom->createElement('input');
        $hiddenInputSubject->setAttribute('type', 'hidden');
        $hiddenInputSubject->setAttribute('name', 'subject');
        $hiddenInputSubject->setAttribute('value', 'server');

        $hiddenInputServer = $dom->createElement('input');
        $hiddenInputServer->setAttribute('type', 'hidden');
        $hiddenInputServer->setAttribute('name', 'server');
        $hiddenInputServer->setAttribute('value', $serverId);

        $hiddenInputSubmit = $dom->createElement('input');
        $hiddenInputSubmit->setAttribute('type', 'submit');
        $hiddenInputSubmit->setAttribute('value', _('Export'));

        $hiddenInputs->appendChild($hiddenInputAction);
        $hiddenInputs->appendChild($hiddenInputSubject);
        $hiddenInputs->appendChild($hiddenInputServer);
        $hiddenInputs->appendChild($hiddenInputSubmit);

        $form->appendChild($hiddenInputs);

        $body->appendChild($form);

        return $body;
    }
}
