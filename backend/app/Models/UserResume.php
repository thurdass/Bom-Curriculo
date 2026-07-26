<?php

namespace App\Models;

use App\Enums\UserResumeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id',
    'original_file_path_cv',
    'original_file_path_linkedin',
    'processed_file_path',
    'status',
    'processed_at',
    'observation',
])]
class UserResume extends Model
{
    use HasUuids;

    /**
     * Append attributes.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'download_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UserResumeEnum::class,
            'processed_at' => 'datetime',
        ];
    }

    /**
     * User relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resume analytic relationship.
     */
    public function analytic(): HasOne
    {
        return $this->hasOne(
            ResumeAnalytic::class,
            'user_resume_id',
            'id'
        );
    }

    /**
     * Download URL accessor.
     */
    public function getDownloadUrlAttribute(): ?string
    {
        if (empty($this->processed_file_path)) {
            return null;
        }

        if (! Storage::exists($this->processed_file_path)) {
            return null;
        }

        return Storage::temporaryUrl($this->processed_file_path, now()->addMinutes(30));
    }

    /**
     * Check if resume has processed document.
     */
    public function hasGeneratedResume(): bool
    {
        return ! empty($this->processed_file_path)
            && Storage::exists($this->processed_file_path);
    }

    /**
     * Check if resume is finished.
     */
    public function isFinished(): bool
    {
        return $this->status === UserResumeEnum::READY;
    }
}
