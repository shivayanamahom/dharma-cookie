<?php
declare(strict_types=1);

namespace Dharma\Plugin\System\DharmaCookie\Extension;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * Уведомление об использовании куки: заголовок, текст со ссылками (слова в [скобках]) и одна кнопка.
 * Без зависимостей и без базы: CSS и JS (assets/) выводятся inline в <head> сайта.
 * Баннер строится в браузере из JSON-настроек, поэтому HTML страницы не зависит от куки и кэш не ломается.
 * Согласие хранится в куке dharma_cookie (значение = версия политики); смена версии показывает баннер заново.
 * Ссылки/кнопки с атрибутом data-cookie-settings снова показывают баннер; в JS доступен window.DharmaCookie.
 */
final class DharmaCookie extends CMSPlugin implements SubscriberInterface
{
    protected $allowLegacyListeners = false;

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onBeforeCompileHead' => 'inject'];
    }

    public function inject(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $doc = $app->getDocument();

        if (!$doc instanceof HtmlDocument || $app->getInput()->getCmd('tmpl') === 'component') {
            return;
        }

        $dir = \dirname(__DIR__, 2) . '/assets/';
        $css = $this->minify((string) @file_get_contents($dir . 'banner.css'));
        $js  = $this->minify((string) @file_get_contents($dir . 'banner.js'));

        if ($css === '' || $js === '') {
            return;
        }

        $custom = trim(str_replace('<', '', (string) $this->params->get('custom_css', '')));

        if ($custom !== '') {
            $css .= $this->minify($custom);
        }

        $config = [
            'n'  => 'dharma_cookie',
            'v'  => (string) $this->params->get('policy_version', '1'),
            'd'  => max(1, min(730, (int) $this->params->get('lifetime', 365))),
            'p'  => Uri::root(true) . '/',
            't'  => (int) $this->params->get('test_mode', 0) === 1,
            'pos' => $this->choice('position', ['bl', 'br', 'bar'], 'bl'),
            'th' => $this->choice('theme', ['dark', 'light'], 'dark'),
            'ti' => $this->text('title', 'TITLE'),
            'tx' => $this->paragraphs($this->text('text', 'TEXT')),
            'b'  => $this->text('button_text', 'BUTTON'),
            'bc' => $this->cssClasses('button_class'),
            'ic' => $this->cssClasses('button_icon_class'),
        ];

        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);

        $wa = $doc->getWebAssetManager();
        $wa->addInlineStyle($css, ['name' => 'plg_system_dharmacookie.css']);
        $wa->addInlineScript('(function(c){' . $js . '})(' . $json . ');', ['name' => 'plg_system_dharmacookie.js']);
    }

    /** Текст из параметра плагина, а если он пуст, то из языкового файла (так работают ru-RU и en-GB). */
    private function text(string $param, string $key): string
    {
        $value = trim((string) $this->params->get($param, ''));

        return $value !== '' ? $value : Text::_('PLG_SYSTEM_DHARMACOOKIE_DEFAULT_' . $key);
    }

    /**
     * Делит текст на абзацы (одна строка = один абзац). Слова в квадратных скобках по порядку становятся ссылками:
     * первое ведёт на link1_url, второе на link2_url; без подходящего адреса остаётся простой текст.
     * Наружу отдаёт формат [слово](адрес), который разбирает assets/banner.js.
     *
     * @return string[]
     */
    private function paragraphs(string $text): array
    {
        $urls   = [$this->url((string) $this->params->get('link1_url', '')), $this->url((string) $this->params->get('link2_url', ''))];
        $index  = 0;
        $result = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $result[] = (string) preg_replace_callback(
                '/\[([^\]]+)\]/u',
                static function (array $m) use ($urls, &$index): string {
                    $url = $urls[$index++] ?? '';

                    return $url === '' ? $m[1] : '[' . $m[1] . '](' . str_replace([' ', '(', ')'], ['%20', '%28', '%29'], $url) . ')';
                },
                $line
            );
        }

        return $result;
    }

    /** Список CSS-классов из параметра: только буквы, цифры, дефис, подчёркивание и пробелы. */
    private function cssClasses(string $param): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9_ -]/', '', (string) $this->params->get($param, '')));
    }

    private function choice(string $param, array $allowed, string $default): string
    {
        $value = (string) $this->params->get($param, $default);

        return \in_array($value, $allowed, true) ? $value : $default;
    }

    /** Пропускает только http(s)-адреса и пути с корня сайта; относительный путь привязывается к корню Joomla. */
    private function url(string $url): string
    {
        $url = trim($url);

        // Пробелы, управляющие символы и обратный слэш браузер при разборе адреса молча превращает в другой адрес.
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) && !preg_match('#^https?://#i', $url)) {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $url) || $url[0] === '/') {
            return $url;
        }

        return Uri::root(true) . '/' . ltrim($url, '/');
    }

    /** Убирает комментарии и сворачивает переносы в пробел: исходники assets/* написаны без // и с точками с запятой. */
    private function minify(string $code): string
    {
        $code = (string) preg_replace('#/\*.*?\*/#s', '', $code);

        return trim((string) preg_replace('/\s*\n\s*/', ' ', $code));
    }
}
