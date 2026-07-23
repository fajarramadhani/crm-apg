<?php

namespace App\Services;

class KnowledgeBaseRedactionService
{
    /**
     * Clean and sanitize HTML/Markdown content to prevent Stored XSS and remove sensitive data.
     */
    public function sanitize(string $content): string
    {
        // 1. Remove dangerous tags: script, iframe, object, embed, applet
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $content);
        $content = preg_replace('/<object\b[^>]*>(.*?)<\/object>/is', '', $content);
        $content = preg_replace('/<embed\b[^>]*>(.*?)<\/embed>/is', '', $content);

        // 2. Remove dangerous event handlers: on* attributes
        $content = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $content);

        // 3. Remove dangerous protocols in links/sources: javascript:, data:
        $content = preg_replace('/(href|src)\s*=\s*["\']?\s*(javascript|data):[^"\'>]*["\']?/i', '$1="#"', $content);

        // 4. Redact sensitive patterns: API tokens, private keys, .env values, credentials
        $content = $this->redactSensitivePatterns($content);

        return $content;
    }

    /**
     * Redact sensitive fields from draft creation from tickets.
     */
    public function sanitizeDraftText(string $text): string
    {
        // Redact personal details like emails, phone numbers
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i', '[REDACTED_EMAIL]', $text);
        $text = preg_replace('/(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4,6}/', '[REDACTED_PHONE]', $text);

        // Redact API tokens, private keys, credentials
        $text = $this->redactSensitivePatterns($text);

        return $text;
    }

    private function redactSensitivePatterns(string $text): string
    {
        // Redact JWT/API keys
        $text = preg_replace('/(bearer\s+[a-zA-Z0-9_\-\.]{20,})/i', 'bearer [REDACTED_TOKEN]', $text);
        $text = preg_replace('/(api[-_]?key|secret|password|passwd|token)\s*[:=]\s*["\']?[a-zA-Z0-9_\-\.]{8,}["\']?/i', '$1=[REDACTED]', $text);

        // Redact Private Keys
        $text = preg_replace('/-----BEGIN [A-Z ]+ PRIVATE KEY-----(.*?)-----END [A-Z ]+ PRIVATE KEY-----/s', '[REDACTED PRIVATE KEY]', $text);

        // Redact Database/Internal storage path leaks
        $text = preg_replace('/(storage\/[a-zA-Z0-9_\-\/]+\.[a-z]{3,4})/i', '[REDACTED_PATH]', $text);

        return $text;
    }
}
