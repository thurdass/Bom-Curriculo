<?php

namespace App\Services\Resume\Documents;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocumentTextExtractor
{
    public function __construct(private PdfTextReader $pdf, private DocxTextReader $docx) {}

    public function extract(?string $storedPath, bool $pdfOnly = false): string
    {
        $disk = Storage::disk('local');
        if (! $storedPath || ! $disk->exists($storedPath)) {
            throw new RuntimeException('Resume document file is missing.', 422);
        }
        if ($disk->size($storedPath) > config('resume.documents.max_file_bytes')) {
            throw new RuntimeException('The document exceeds the file size limit.', 422);
        }
        $extension = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
        $path = $disk->path($storedPath);
        $text = match ($extension) {
            'pdf' => $this->pdf->read($path),
            'docx' => $pdfOnly ? throw new RuntimeException('LinkedIn resume must be a PDF file.', 422) : $this->docx->read($path),
            default => throw new RuntimeException('Resume must be a PDF or DOCX file.', 422),
        };

        return trim(preg_replace('/\n{3,}/u', "\n\n", str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $text)));
    }
}
