<?php

namespace App\Services;

use RuntimeException;

// =============================================================================
final class MailparseEmailBodyExtractor implements EmailBodyExtractor {
	// =========================================================================
    public function extract(string $eml): string {
        $message = mailparse_msg_create();

        if (! mailparse_msg_parse($message, $eml)) {
            throw new RuntimeException('Unable to parse EML message.');
        }

        $structure = mailparse_msg_get_structure($message);

        $plain = null;
        $html = null;

        foreach ($structure as $partId) {
            $part = mailparse_msg_get_part($message, $partId);
            $data = mailparse_msg_get_part_data($part);

            $contentType = strtolower($data['content-type'] ?? '');

            if (! in_array($contentType, ['text/plain', 'text/html'], true)) {
                continue;
            }

            $startingPos = $data['starting-pos-body'] ?? null;
            $endingPos = $data['ending-pos-body'] ?? null;

            if ($startingPos === null || $endingPos === null) {
                continue;
            }

            $body = substr(
                $eml,
                $startingPos,
                $endingPos - $startingPos
            );

            $encoding = strtolower($data['transfer-encoding'] ?? '');

            $body = match ($encoding) {
                'base64' => base64_decode($body, true) ?: '',
                'quoted-printable' => quoted_printable_decode($body),
                default => $body,
            };

            if ($contentType === 'text/plain' && trim($body) !== '') {
                $plain ??= $body;
            }

            if ($contentType === 'text/html' && trim($body) !== '') {
                $html ??= $body;
            }
        }

        mailparse_msg_free($message);

        if ($plain !== null) {
            return $this->cleanPlainText($plain);
        }

        if ($html !== null) {
            return $this->cleanHtml($html);
        }

        throw new RuntimeException('No usable message body found.');
    }

	// =========================================================================
    private function cleanPlainText(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse excessive whitespace without destroying line structure.
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

	// =========================================================================
    private function cleanHtml(string $html): string {
        // Remove scripts and styles.
        $html = preg_replace(
            '#<(script|style)\b[^>]*>.*?</\1>#is',
            '',
            $html
        );

        $text = strip_tags(
            str_replace(
                ['<br>', '<br/>', '<br />', '</tr>', '</p>', '</div>'],
                "\n",
                $html
            )
        );

        return $this->cleanPlainText($text);
    }
}
