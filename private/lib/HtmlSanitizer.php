<?php
// private/lib/HtmlSanitizer.php
declare(strict_types=1);
namespace Kuko;

final class HtmlSanitizer
{
    // Structural tags added for the admin-editable privacy.body / faq.items
    // content blocks: h2 (privacy headings), div (<div class="faq">),
    // details + summary (FAQ accordion). Only tags actually present in those
    // blocks were added — no script/iframe/object/embed/img/span/section.
    private const ALLOWED_TAGS = [
        'b','i','strong','em','a','p','ul','ol','li','br','h3','h4',
        'h2','div','details','summary',
    ];

    // Safe href prefixes. Anything not matching is dropped (incl.
    // javascript:, data:, vbscript:, and any unlisted scheme).
    // Case-insensitive; value is trimmed before testing.
    private const HREF_RE = '#^(/(?![/\\\\])|\#|\./|https?://|mailto:|tel:)#i';

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
                    // Allow only the safe prefixes; reject javascript:/data:/
                    // vbscript: and every other scheme. Trim first so leading
                    // whitespace can't smuggle a scheme past the anchor.
                    $val = trim($attr->value);
                    if (!preg_match(self::HREF_RE, $val)) {
                        $child->removeAttribute($attr->name);
                    }
                } elseif ($name === 'class') {
                    // class is presentational only and safe; needed for
                    // <div class="faq">, <details class="faq__item">,
                    // <h2 class="legal-h2">, <p class="section__lead">.
                    // No id / on* / style / srcdoc — those stay stripped.
                    continue;
                } else {
                    $child->removeAttribute($attr->name);
                }
            }
            self::sanitizeNode($child);
        }
    }
}
