<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Texte riche des descriptions et commentaires (HTML produit par l'éditeur Tiptap).
 * Tout ce qui est enregistré passe par sanitize() : seules les balises et attributs
 * de l'éditeur sont conservés, le reste (scripts, styles, liens javascript:…) est retiré.
 */
class RichText
{
    /** Balises autorisées et leurs attributs. */
    private const TAGS = [
        'p' => [], 'br' => [], 'hr' => [],
        'h1' => [], 'h2' => [], 'h3' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 's' => [], 'u' => [],
        'code' => [], 'pre' => [], 'blockquote' => [],
        'mark' => ['style', 'data-color'],
        'span' => ['style', 'data-type', 'data-id', 'data-label'],
        'a' => ['href'],
        'img' => ['src', 'alt', 'title'],
        'ul' => ['data-type'], 'ol' => ['start'],
        'li' => ['data-type', 'data-checked'],
        'label' => [], 'input' => ['type', 'checked'], 'div' => [],
    ];

    /** Balises supprimées avec leur contenu (les autres balises inconnues sont « déballées »). */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math', 'head', 'title', 'meta', 'link', 'form', 'button', 'select', 'textarea', 'noscript'];

    /** Propriétés CSS conservées (couleurs et taille du texte). */
    private const STYLES = ['color', 'background-color', 'font-size'];

    /** Images envoyées depuis l'éditeur (voir EditorImageController). */
    public const IMAGE_PATH = '#^/projects/\d+/images/[A-Za-z0-9]+\.(png|jpe?g|gif|webp)$#';

    /** Nettoie le HTML de l'éditeur. Un texte qui ne commence pas par une balise (ancienne saisie, règle…) devient des paragraphes. */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        if (! str_starts_with(ltrim($html), '<')) {
            return self::fromPlain($html);
        }

        $doc = self::load($html);
        $body = $doc->getElementsByTagName('body')->item(0);
        if (! $body) {
            return null;
        }
        self::clean($body);

        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        $out = trim($out);

        return self::isEmpty($out) ? null : $out;
    }

    /** Texte brut → paragraphes HTML (retours à la ligne conservés). */
    public static function fromPlain(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text));

        return implode('', array_map(
            fn ($p) => '<p>'.preg_replace('/\R/', '<br>', e($p)).'</p>',
            $paragraphs,
        ));
    }

    /** Version texte (e-mails, aperçus). Les mentions restent sous la forme « @Nom ». */
    public static function toText(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }
        $text = preg_replace(['#<br\s*/?>#i', '#</(p|h[1-6]|li|blockquote|pre|div)>#i', '#<img[^>]*>#i'], ["\n", "\n", '[image] '], $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * Identifiants des collaborateurs mentionnés avec « @ ».
     *
     * @return list<int>
     */
    public static function mentionedIds(?string $html): array
    {
        if (! $html || ! str_contains($html, 'mention')) {
            return [];
        }
        $ids = [];
        foreach ((new DOMXPath(self::load($html)))->query('//span[@data-type="mention"]') as $span) {
            $id = (int) $span->getAttribute('data-id');
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function isEmpty(string $html): bool
    {
        return ! str_contains($html, '<img') && ! str_contains($html, '<hr')
            && trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{A0}") === '';
    }

    private static function load(string $html): DOMDocument
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc;
    }

    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }
            if (! $child instanceof DOMElement) {
                $node->removeChild($child); // commentaires, CDATA…

                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            self::clean($child);

            if (! array_key_exists($tag, self::TAGS)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            if (! self::cleanAttributes($child, $tag)) {
                $node->removeChild($child);
            }
        }
    }

    /** Retire les attributs non autorisés ; renvoie false si l'élément doit disparaître. */
    private static function cleanAttributes(DOMElement $el, string $tag): bool
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = trim($attr->value);
            $keep = in_array($name, self::TAGS[$tag], true) && match ($name) {
                'href' => (bool) preg_match('#^(https?://|mailto:|/(?!/))#i', $value),
                'src' => (bool) preg_match('#^https://#i', $value) || (bool) preg_match(self::IMAGE_PATH, $value),
                'style' => ($value = self::cleanStyle($value)) !== '',
                'data-id', 'start' => ctype_digit($value),
                'data-checked' => in_array($value, ['true', 'false'], true),
                'type' => $value === 'checkbox',
                default => true,
            };
            if ($keep) {
                $el->setAttribute($name, $value);
            } else {
                $el->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer nofollow');
        }

        return match ($tag) {
            'img' => $el->hasAttribute('src'),
            'input' => $el->getAttribute('type') === 'checkbox',
            default => true,
        };
    }

    private static function cleanStyle(string $style): string
    {
        $kept = [];
        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_map('trim', explode(':', $declaration, 2) + [1 => '']);
            $property = strtolower($property);
            $valid = match ($property) {
                'color', 'background-color' => preg_match('/^(#[0-9a-f]{3,8}|rgba?\([\d\s.,%]+\)|[a-z]{3,20})$/i', $value),
                'font-size' => preg_match('/^\d{1,3}(\.\d+)?(px|em|rem|%)$/', $value),
                default => false,
            };
            if ($valid && in_array($property, self::STYLES, true)) {
                $kept[] = "{$property}: {$value}";
            }
        }

        return implode('; ', $kept);
    }
}
