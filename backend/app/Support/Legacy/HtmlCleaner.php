<?php

namespace App\Support\Legacy;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Normalises legacy WordPress/Gemini-pasted HTML: keeps semantic tags only, drops inline
 * styles/classes/data-* attributes, unwraps spans and wrapper divs, and makes internal links relative.
 */
class HtmlCleaner
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'em' => [], 'u' => [], 'sub' => [], 'sup' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'a' => ['href', 'target', 'rel'], 'img' => ['src', 'alt', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'figure' => [], 'figcaption' => [], 'iframe' => ['src', 'title', 'allowfullscreen'],
    ];

    private const RENAME = ['b' => 'strong', 'i' => 'em', 'h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4'];

    private const DROP = ['script', 'style', 'noscript', 'form', 'input', 'button', 'svg', 'link', 'meta'];

    /** @param  callable(string): ?string|null  $imageResolver  maps a legacy image URL to its new URL */
    public function __construct(private readonly array $siteHosts, private $imageResolver = null) {}

    public function clean(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        $html = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], self::restoreEmails($html));
        if (! preg_match('/<(p|ul|ol|h[1-6]|div|table)\b/i', $html)) {
            $html = '<p>'.preg_replace('/\n{2,}/', '</p><p>', trim($html)).'</p>';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementById('__root');
        if (! $root) {
            return null;
        }

        $this->walk($root);

        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('.//p[not(normalize-space()) and not(.//img) and not(.//iframe)]', $root) as $empty) {
            $empty->parentNode?->removeChild($empty);
        }

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        $out = preg_replace(
            ['/(&nbsp;|\x{00A0})+/u', '/\s+/u', '/\s*(<\/?(?:p|ul|ol|li|h[2-4]|table|thead|tbody|tr|td|th|blockquote|figure|figcaption|br)\b[^>]*>)\s*/i'],
            [' ', ' ', '$1'],
            $out,
        );

        return trim($out) ?: null;
    }

    /** Cloudflare's scraped "email protection" leaves "[email protected]" and a dead /cdn-cgi link; put the real address back. */
    public static function restoreEmails(string $html): string
    {
        $decode = function (string $hex): ?string {
            $bytes = array_map('hexdec', str_split($hex, 2));
            $key = array_shift($bytes);
            $email = implode('', array_map(fn (int $b) => chr($b ^ $key), $bytes));

            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        };

        $html = preg_replace_callback(
            '#<a\b[^>]*href="[^"]*/cdn-cgi/l/email-protection\#([0-9a-f]+)"[^>]*>.*?</a>#is',
            fn (array $m) => ($email = $decode($m[1])) ? '<a href="mailto:'.e($email).'">'.e($email).'</a>' : $m[0],
            $html,
        );

        return preg_replace_callback(
            '#<(span|a)\b[^>]*data-cfemail="([0-9a-f]+)"[^>]*>.*?</\1>#is',
            fn (array $m) => ($email = $decode($m[2])) ? e($email) : $m[0],
            $html,
        );
    }

    private function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $node->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            if (isset(self::RENAME[$tag])) {
                $child = $this->rename($child, self::RENAME[$tag]);
                $tag = self::RENAME[$tag];
            }

            $this->walk($child);

            if (! array_key_exists($tag, self::ALLOWED)) {
                $this->unwrap($child, block: in_array($tag, ['div', 'section', 'article'], true));

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                if (! in_array($attr->name, self::ALLOWED[$tag], true)) {
                    $child->removeAttribute($attr->name);
                }
            }

            match ($tag) {
                'a' => $this->fixLink($child),
                'img' => $this->fixImage($child),
                'iframe' => $this->fixIframe($child),
                default => null,
            };
        }
    }

    private function fixLink(DOMElement $a): void
    {
        $href = trim($a->getAttribute('href'));
        if ($href === '' || str_starts_with($href, 'javascript:')) {
            $this->unwrap($a);

            return;
        }

        $host = parse_url($href, PHP_URL_HOST);
        if ($host && in_array(strtolower(preg_replace('/^www\./', '', $host)), $this->siteHosts, true)) {
            $path = '/'.trim((string) parse_url($href, PHP_URL_PATH), '/');
            $a->setAttribute('href', $path === '/' ? '/' : $path);
            $a->removeAttribute('target');
            $a->removeAttribute('rel');

            return;
        }

        if ($host) {
            $a->setAttribute('target', '_blank');
            $a->setAttribute('rel', 'noopener');
        }
    }

    private function fixImage(DOMElement $img): void
    {
        $src = trim($img->getAttribute('src'));
        $resolved = $src && $this->imageResolver ? ($this->imageResolver)($src) : $src;

        if (! $resolved) {
            $img->parentNode?->removeChild($img);

            return;
        }

        $img->setAttribute('src', $resolved);
        $img->setAttribute('loading', 'lazy');
        if (! $img->hasAttribute('alt')) {
            $img->setAttribute('alt', '');
        }
    }

    private function fixIframe(DOMElement $iframe): void
    {
        if (! preg_match('#^https://(www\.)?(youtube(-nocookie)?\.com|player\.vimeo\.com|www\.google\.com/maps)/#', $iframe->getAttribute('src'))) {
            $iframe->parentNode?->removeChild($iframe);

            return;
        }

        $iframe->setAttribute('loading', 'lazy');
    }

    private function rename(DOMElement $el, string $tag): DOMElement
    {
        $new = $el->ownerDocument->createElement($tag);
        while ($el->firstChild) {
            $new->appendChild($el->firstChild);
        }
        $el->parentNode->replaceChild($new, $el);

        return $new;
    }

    private function unwrap(DOMElement $el, bool $block = false): void
    {
        $parent = $el->parentNode;
        if (! $parent) {
            return;
        }

        $hasBlockChildren = false;
        foreach ($el->childNodes as $c) {
            if ($c instanceof DOMElement && in_array($c->tagName, ['p', 'ul', 'ol', 'h2', 'h3', 'h4', 'table', 'blockquote', 'figure'], true)) {
                $hasBlockChildren = true;
                break;
            }
        }

        if ($block && ! $hasBlockChildren && trim($el->textContent) !== '') {
            $p = $el->ownerDocument->createElement('p');
            while ($el->firstChild) {
                $p->appendChild($el->firstChild);
            }
            $parent->replaceChild($p, $el);

            return;
        }

        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }
}
