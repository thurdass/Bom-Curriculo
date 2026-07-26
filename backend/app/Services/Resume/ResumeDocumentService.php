<?php

namespace App\Services\Resume;

use App\Models\ResumeAnalytic;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResumeDocumentService
{
    /**
     * Storage disk.
     */
    protected string $disk = 'local';

    /**
     * Folder where generated resumes are stored.
     */
    protected string $folder = 'resumes/generated';

    /**
     * Generate document.
     */
    public function generate(
        ResumeAnalytic $resume,
        string $format = 'pdf'
    ): string {

        return match ($format) {

            'pdf' => $this->generatePdf($resume),

            default => throw new Exception(
                "Unsupported document format [{$format}]"
            )

        };

    }

    /**
     * Generate ATS PDF.
     */
    protected function generatePdf(
        ResumeAnalytic $resume
    ): string {

        $filename = $this->filename(
            $resume,
            'pdf'
        );

        $path = $this->folder.'/'.$filename;

        $pdf = Pdf::loadView(
            'exports.ats',
            [
                'resume' => $resume,
            ]
        );

        $pdf->setPaper(
            'a4',
            'portrait'
        );

        Storage::disk($this->disk)->put(
            $path,
            $pdf->output()
        );

        return $path;

    }

    /**
     * Build unique filename.
     */
    protected function filename(
        ResumeAnalytic $resume,
        string $extension
    ): string {

        $name = data_get(
            $resume->header,
            'name',
            'resume'
        );

        $name = Str::slug($name);

        return sprintf(
            '%s_%s.%s',
            $name,
            now()->format('YmdHis'),
            $extension
        );

    }

    /**
     * Delete old generated document.
     */
    public function delete(
        ?string $path
    ): bool {

        if (
            empty($path)
        ) {

            return false;

        }

        if (
            ! Storage::disk($this->disk)->exists($path)
        ) {

            return false;

        }

        return Storage::disk($this->disk)
            ->delete($path);

    }

    /**
     * Regenerate document.
     */
    public function regenerate(
        ResumeAnalytic $resume,
        ?string $oldPath
    ): string {

        $this->delete(
            $oldPath
        );

        return $this->generate(
            $resume
        );

    }

    /**
     * Check document exists.
     */
    public function exists(
        string $path
    ): bool {

        return Storage::disk($this->disk)
            ->exists($path);

    }

    /**
     * Temporary download URL.
     */
    public function temporaryUrl(
        string $path,
        int $minutes = 1440
    ): ?string {

        if (
            ! $this->exists($path)
        ) {

            return null;

        }

        return Storage::disk($this->disk)
            ->temporaryUrl(
                $path,
                now()->addMinutes(
                    $minutes
                )
            );

    }

    /**
     * Absolute file path.
     */
    public function fullPath(
        string $path
    ): string {

        return Storage::disk($this->disk)
            ->path($path);

    }
}
