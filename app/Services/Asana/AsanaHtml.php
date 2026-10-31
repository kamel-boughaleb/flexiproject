<?php

namespace App\Services\Asana;

use App\Models\User;
use App\Support\RichText;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Convertit le texte riche d'Asana (html_notes, html_text) au format de l'éditeur de Flexi Projets.
 * Asana renvoie « <body>Ligne 1\nLigne 2 <strong>gras</strong><ul>…</ul></body> » : les retours
 * à la ligne deviennent des paragraphes et les mentions de personnes des mentions « @ » cliquables.
 */
class AsanaHtml
{
    private const BLOCKS = ['ul', 'ol', 'h1', 'h2', 'h3', 'hr', 'blockquote', 'pre', 'p', 'div', 'table'];

    /**
     * @param  (callable(string $gid): ?User)|null  $userFor  Collaborateur correspondant à un identifiant Asana.
     */
    public static function toRichText(?string $html, ?callable $userFor = null): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        if (! preg_match('#^\s*<body#i', $html)) {
            return RichText::fromPlain($html);
        }

        $inner = preg_replace(['#^\s*<body[^>]*>#i', '#</body>\s*$#i'], '', $html);
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$inner.'</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);

        // Mentions de personnes → mentions Flexi Projets (ou simple texte « @Nom »).
        foreach (iterator_to_array($xpath->query('//a[@data-asana-type="user"]')) as $link) {
            /** @var DOMElement $link */
            $user = $userFor ? $userFor($link->getAttribute('data-asana-gid')) : null;
            $label = ltrim(trim($link->textContent), '@');
            if ($user) {
                $span = $doc->createElement('span');
                $span->setAttribute('data-type', 'mention');
                $span->setAttribute('data-id', (string) $user->id);
                $span->setAttribute('data-label', $user->name);
                $span->appendChild($doc->createTextNode('@'.$user->name));
                $link->parentNode->replaceChild($span, $link);
            } else {
                $link->parentNode->replaceChild($doc->createTextNode('@'.$label), $link);
            }
        }
        // Les images intégrées à Asana ne sont accessibles qu'avec une session Asana : elles sont importées en pièces jointes.
        foreach (iterator_to_array($xpath->query('//img')) as $img) {
            $img->parentNode->removeChild($img);
        }

        $body = $doc->getElementsByTagName('body')->item(0);
        $out = '';
        $paragraph = '';
        $flush = function () use (&$out, &$paragraph) {
            if (trim(html_entity_decode(strip_tags($paragraph), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{A0}") !== '') {
                $out .= '<p>'.$paragraph.'</p>';
            }
            $paragraph = '';
        };

        foreach (iterator_to_array($body->childNodes) as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                foreach (explode("\n", $node->textContent) as $i => $line) {
                    if ($i > 0) {
                        $flush();
                    }
                    $paragraph .= htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            } elseif ($node instanceof DOMElement && in_array(strtolower($node->tagName), self::BLOCKS, true)) {
                $flush();
                $out .= $doc->saveHTML($node);
            } elseif ($node instanceof DOMElement) {
                $paragraph .= str_replace("\n", '<br>', $doc->saveHTML($node));
            }
        }
        $flush();

        return RichText::sanitize($out);
    }
}
