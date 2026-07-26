<?php

namespace App\Services\Bot\DTO;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class BotCallbackDto
{
    private const REQUIREMENTS = [
        'header',
        'score',
        'experiences',
        'professional_summary',
        'projects',
        'qualifications',
        'skills',
        'others',
    ];

    public static string $user_resume_id;

    public static array $result;

    public static null|string|array|object $error = null;

    public static int $score = 0;

    public static ?string $professional_summary = null;

    public static array $header = [];

    public static array $experiences = [];

    public static array $projects = [];

    public static array $qualifications = [];

    public static array $skills = [];

    public static array $languages = [];

    public static array $others = [];

    public function __construct(
    ) {}

    public static function fromData(array|object $data, string $resumeId): self
    {
        $dataArray = is_object($data) ? (array) $data : $data;

        foreach (self::REQUIREMENTS as $requirement) {
            if (! array_key_exists($requirement, $dataArray)) {

                Log::channel('bot')->error('BOT DTO fail', [
                    'error' => "Missing required field from BOT: {$requirement}",
                    'payload' => $dataArray,
                ]);

                throw new InvalidArgumentException("Missing required field: {$requirement}");
            }
        }

        self::$user_resume_id = $resumeId;
        self::$result = $dataArray;
        self::$error = $dataArray['error'] ?? null;

        return new self;
    }

    public static function handle(): self
    {
        self::processScore();
        self::processProfessionalSummary();
        self::processHeader();
        self::processSkills();
        self::processExperiences();
        self::processProjects();
        self::processQualifications();
        self::processLanguages();
        self::proccessOthers();

        return new self;
    }

    public function debug(): void
    {
        dd($this);
    }

    public static function mustRequestJson(): array
    {
        return [
            'header' => [],
            'score' => 0,
            'experiences' => [],
            'professional_summary' => null,
            'projects' => [],
            'qualifications' => [],
            'skills' => [],
            'languages' => [],
            'others' => [],
        ];
    }

    public static function toArray(): array
    {
        return [
            'user_resume_id' => self::$user_resume_id,
            'error' => self::$error,
            'score' => self::$score,
            'professional_summary' => self::$professional_summary,
            'header' => self::$header,
            'experiences' => self::$experiences,
            'projects' => self::$projects,
            'qualifications' => self::$qualifications,
            'skills' => self::$skills,
            'languages' => self::$languages,
            'others' => self::$others,
        ];
    }

    protected static function processScore(): void
    {
        self::$score = (int) (self::$result['score'] ?? 0);
    }

    protected static function processProfessionalSummary(): void
    {
        self::$professional_summary = self::$result['professional_summary'] ?? null;
    }

    protected static function processHeader(): void
    {
        self::$header = self::$result['header'] ?? [];
    }

    protected static function processSkills(): void
    {
        self::$skills = self::$result['skills'] ?? [];
    }

    protected static function processExperiences(): void
    {
        self::$experiences = self::$result['experiences'] ?? [];
    }

    protected static function processProjects(): void
    {
        self::$projects = self::$result['projects'] ?? [];
    }

    protected static function processQualifications(): void
    {
        self::$qualifications = self::$result['qualifications'] ?? [];
    }

    protected static function processLanguages(): void
    {
        self::$languages = self::$result['languages'] ?? [];
    }

    protected static function proccessOthers(): void
    {
        self::$others = self::$result['others'] ?? [];
    }
}
