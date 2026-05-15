<?php
// private/lib/HtmlSanitizer.php
declare(strict_types=1);
namespace Kuko;

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = ['b','i','strong','em','a','p','ul','ol','li','br','h3','h4'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') return '';

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            // XML prolog forces libxml to parse input as UTF-8 (not Latin-1)
            // wrapper gives a stable retrieval anchor and stops loadHTML auto-wrapping in <html><body>
            '<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $dom->getElementById('__root');
        if ($root === null) return '';

        self::sanitizeNode($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }
        return trim($out);
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }
            if (!($child instanceof \DOMElement)) {
                $node->removeChild($child);
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // Sanitize the disallowed element's subtree first, so any
                // unwrapped descendants are already clean, then unwrap.
                self::sanitizeNode($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if ($tag === 'a' && $name === 'href') {
                    $val = trim($attr->value);
                    if (!preg_match('#^(https?://|mailto:|tel:)#i', $val)) {
                        $child->removeAttribute($attr->name);
                    }
                } else {
                    $child->removeAttribute($attr->name);
                }
            }
            self::sanitizeNode($child);
        }
    }
}
