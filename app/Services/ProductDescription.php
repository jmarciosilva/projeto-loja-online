<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use League\CommonMark\CommonMarkConverter;

/** Converts legacy Markdown and sanitizes the small HTML subset used by products. */
class ProductDescription
{
    private const ELEMENTS = ['p', 'strong', 'em', 'u', 'h2', 'h3', 'ul', 'ol', 'li', 'a', 'br'];

    public function render(?string $content): string
    {
        if (! filled($content)) {
            return '';
        }

        // Older records are plain Markdown. Convert only when no HTML markup is present.
        if (! preg_match('/<\/?[a-z][^>]*>/i', $content)) {
            $content = (string) (new CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 50,
            ]))->convert($content);
        }

        return $this->sanitize($content);
    }

    public function sanitize(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementsByTagName('div')->item(0);

        if ($root === null) {
            return '';
        }

        $this->cleanChildren($root);
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            $this->cleanChildren($node);

            if (! in_array($tag, self::ELEMENTS, true)) {
                // Drop dangerous embedded-content elements and unwrap harmless formatting.
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math'], true)) {
                    $node->parentNode?->removeChild($node);
                } else {
                    $parentNode = $node->parentNode;
                    if ($parentNode !== null) {
                        while ($node->firstChild !== null) {
                            $parentNode->insertBefore($node->firstChild, $node);
                        }
                        $parentNode->removeChild($node);
                    }
                }

                continue;
            }

            foreach (iterator_to_array($node->attributes) as $attribute) {
                if ($tag !== 'a' || $attribute->name !== 'href' || ! $this->safeHref($attribute->value)) {
                    $node->removeAttribute($attribute->name);
                }
            }
        }
    }

    private function safeHref(string $href): bool
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $href !== '' && preg_match('/^(?:https?:|mailto:|\/|#)/i', $href) === 1
            && ! preg_match('/[\x00-\x20]/', $href);
    }
}
