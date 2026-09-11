<?php

namespace App\Console\Commands;

use App\Services\EmailBodyExtractor;
use App\Services\OpenAIFlightConfirmationExtractor;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

// =============================================================================
final class ParseFlightEmail extends Command
{
    protected $signature = 'flight:parse {file : Path to an .eml file}';
    protected $description = 'Parse a flight confirmation EML and display the result as JSON';

	// =========================================================================
    public function handle(
        EmailBodyExtractor $bodyExtractor,
        OpenAIFlightConfirmationExtractor $extractor,
    ): int {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        try {
            $eml = file_get_contents($path);

            if ($eml === false) {
                throw new \RuntimeException("Unable to read file: {$path}");
            }

            $body = $bodyExtractor->extract($eml);

            $confirmation = $extractor->extract($body);

            $json = json_encode(
                $confirmation->toArray(),
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            );

            $this->line($json);

            return self::SUCCESS;

        } catch (JsonException $e) {
            $this->error("JSON error: {$e->getMessage()}");

            return self::FAILURE;

        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
