<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class TicketDescriptionSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li', 'blockquote',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'button', 'svg', 'math'];

    private const INVISIBLE_CHARACTERS = '/[\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u';

    public function sanitize(string $html): string
    {
        // libxml treats unclosed void elements as containers, so remove blocked void tags before parsing.
        $html = preg_replace('/<(?:embed|input)\b[^>]*\/?\s*>/is', '', $html) ?? '';
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);
        if (! $body) {
            return '';
        }

        $this->cleanChildren($body);

        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    public function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace(self::INVISIBLE_CHARACTERS, ' ', $text) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    public function hasMeaningfulText(string $html): bool
    {
        return $this->plainText($this->sanitize($html)) !== '';
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->parentNode?->removeChild($node);

                continue;
            }
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->unwrap($node);

                continue;
            }

            $this->cleanAttributes($node, $tag);
            $this->cleanChildren($node);
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $allowed = match ($tag) {
                'a' => in_array($name, ['href', 'title', 'target', 'rel'], true),
                'p' => $name === 'style',
                'th', 'td' => in_array($name, ['colspan', 'rowspan'], true),
                default => false,
            };

            if (! $allowed) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag === 'p' && $element->hasAttribute('style')) {
            $style = strtolower(trim($element->getAttribute('style')));
            if (! preg_match('/^text-align:\s*(left|center|right|justify);?$/', $style)) {
                $element->removeAttribute('style');
            }
        }

        if ($tag === 'a') {
            $href = trim($element->getAttribute('href'));
            if ($href !== '' && ! preg_match('/^(https?:\/\/|mailto:)/i', $href)) {
                $element->removeAttribute('href');
            }
            if ($element->getAttribute('target') === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            } else {
                $element->removeAttribute('target');
                $element->removeAttribute('rel');
            }
        }

        if (in_array($tag, ['th', 'td'], true)) {
            foreach (['colspan', 'rowspan'] as $attribute) {
                if ($element->hasAttribute($attribute) && ! preg_match('/^[1-9]\d?$/', $element->getAttribute($attribute))) {
                    $element->removeAttribute($attribute);
                }
            }
        }
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
        $this->cleanChildren($parent);
    }
}
