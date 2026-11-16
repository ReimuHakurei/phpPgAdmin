<?php

declare(strict_types=1);

namespace PhpPgAdmin\Website;

use PhpPgAdmin\{Config, Language, Themes, Website, WebsiteComponents};
use PhpPgAdmin\Infrastructure\Http\RequestParameter;

final class Intro extends Website
{
    /**
     * The introduction, which belongs to no server.
     */
    public static function requiresServerLogin(): bool
    {
        return false;
    }

    protected function buildHtmlBody(\DOMDocument $dom): \DOMElement
    {
        $body = parent::buildHtmlBody($dom);

        $body->appendChild(WebsiteComponents::buildTopBar($dom));
        $body->appendChild(WebsiteComponents::buildTrail($dom));
        $body->appendChild(WebsiteComponents::buildRootTabs($dom, 'intro'));

        $h1 = $dom->createElement('h1', self::APP_NAME . ' ' . self::APP_VERSION . ' (PHP ' . PHP_VERSION . ')');
        $body->appendChild($h1);

        $form = $dom->createElement('form');
        $form->setAttribute('method', 'get');
        $form->setAttribute('action', 'intro.php');
        $table = $dom->createElement('table');
        $tr = $dom->createElement('tr');
        $tr->setAttribute('class', 'data1');
        $th = $dom->createElement('th', _('Language'));
        $th->setAttribute('class', 'data');
        $tr->appendChild($th);
        $td = $dom->createElement('td');
        $select = $dom->createElement('select');
        $select->setAttribute('name', 'language');
        $select->setAttribute('onchange', 'this.form.submit()');

        $currentLocale = Config::locale();
        $languageIdsWithLocales = Language::getAvailableLanguageIdsWithLocales();

        foreach ($languageIdsWithLocales as $languageId => $locale) {
            Language::setLocale($locale);
            $option = $dom->createElement('option', _('applang'));
            $option->setAttribute('value', $languageId);
            $option->setAttribute('data-locale', $locale);
            $option->setAttribute('data-language-id', $languageId);

            if ($locale === $currentLocale) {
                $option->setAttribute('selected', 'selected');
            }

            $select->appendChild($option);
        }

        // Reset locale
        Language::setLocale($currentLocale);

        $td->appendChild($select);
        $tr->appendChild($td);
        $table->appendChild($tr);
        $tr = $dom->createElement('tr');
        $tr->setAttribute('class', 'data2');
        $th = $dom->createElement('th', _('Theme'));
        $th->setAttribute('class', 'data');
        $tr->appendChild($th);
        $td = $dom->createElement('td');
        $select = $dom->createElement('select');
        $select->setAttribute('name', 'theme');
        $select->setAttribute('onchange', 'this.form.submit()');
        $themes = Themes::available();

        foreach ($themes as $theme => $label) {
            $option = $dom->createElement('option', $label);
            $option->setAttribute('value', $theme);

            if ($theme === Config::theme()) {
                $option->setAttribute('selected', 'selected');
            }

            $select->appendChild($option);
        }

        $td->appendChild($select);
        $tr->appendChild($td);
        $table->appendChild($tr);
        $form->appendChild($table);
        $body->appendChild($form);

        $p = $dom->createElement('p', _('Welcome to phpPgAdmin.'));
        $body->appendChild($p);

        $ul = $dom->createElement('ul');
        $ul->setAttribute('class', 'intro');
        $li = $dom->createElement('li');
        $a = $dom->createElement('a', _('phpPgAdmin Homepage'));
        $a->setAttribute('href', 'https://github.com/ringostarr80/phpPgAdmin');
        $li->appendChild($a);
        $ul->appendChild($li);
        $li = $dom->createElement('li');
        $a = $dom->createElement('a', _('PostgreSQL Homepage'));
        $a->setAttribute('href', 'https://www.postgresql.org/');
        $li->appendChild($a);
        $ul->appendChild($li);
        $li = $dom->createElement('li');
        $a = $dom->createElement('a', _('Report a Bug'));
        $a->setAttribute('href', 'https://github.com/ringostarr80/phpPgAdmin/issues');
        $li->appendChild($a);
        $ul->appendChild($li);
        $body->appendChild($ul);

        $body->appendChild(WebsiteComponents::buildBackToTopLink($dom));

        $languageParam = RequestParameter::getString('language');

        if (!is_null($languageParam)) {
            $body->appendChild(WebsiteComponents::buildBrowserReloadScript($dom));
        }

        return $body;
    }
}
