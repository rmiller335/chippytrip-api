<?php

namespace App\Services;

// =============================================================================
interface EmailBodyExtractor {
    public function extract(string $eml): string;
}
