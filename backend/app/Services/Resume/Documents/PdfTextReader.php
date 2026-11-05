<?php

namespace App\Services\Resume\Documents;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class PdfTextReader
{
    public function read(string $path): string
    {
        $binary = config('resume.documents.pdftotext_binary');
        if (! is_string($binary) || ! is_executable($binary)) {
            throw new RuntimeException('PDF extraction is not configured.', 503);
        }
        // Argument arrays avoid shell interpretation of user-controlled file names.
        $process = new Process([$binary, '-enc', 'UTF-8', '-nopgbrk', '-layout', $path, '-']);
        $process->setTimeout(config('resume.documents.timeout_seconds'));
        $text = '';
        try {
            $process->run(function ($type, $chunk) use (&$text, $process): void {
                if ($type === Process::OUT) {
                    $text .= $chunk;
                    $process->clearOutput();
                    if (strlen($text) > config('resume.documents.max_text_bytes')) {
                        $process->stop(0);
                        throw new RuntimeException('The extracted document exceeds the text limit.', 422);
                    }
                } else {
                    $process->clearErrorOutput();
                }
            });
        } catch (ProcessTimedOutException $error) {
            throw new RuntimeException('Document extraction exceeded the time limit.', 422, $error);
        }
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Could not read the PDF document.', 422);
        }

        return $text;
    }
}
