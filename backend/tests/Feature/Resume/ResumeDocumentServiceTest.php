<?php

use App\Models\ResumeAnalytic;
use App\Services\Resume\ResumeDocumentService;
use Illuminate\Support\Facades\Storage;

it('generates the ATS PDF when real bot scalar fields are strings or null', function () {
    Storage::fake('local');

    $cases = [
        [
            'name' => 'String Contract Resume',
            'contacts' => '+55 71 99999-0000',
            'emails' => null,
            'technologies' => 'Laravel, React',
        ],
        [
            'name' => 'Null Contract Resume',
            'contacts' => null,
            'emails' => 'candidate@example.test',
            'technologies' => null,
        ],
        [
            'name' => 'Array Compatibility Resume',
            'contacts' => ['+55 71 99999-0000'],
            'emails' => ['candidate@example.test'],
            'technologies' => ['Laravel', 'React'],
        ],
    ];

    foreach ($cases as $case) {
        $resume = new ResumeAnalytic;
        $resume->forceFill([
            'header' => [
                'name' => $case['name'],
                'headline' => null,
                'location' => null,
                'contacts' => $case['contacts'],
                'emails' => $case['emails'],
                'links' => [],
                'summary' => 'Summary from the real bot response shape.',
            ],
            'experiences' => [],
            'projects' => [[
                'title' => 'Contract-safe project',
                'description' => null,
                'url' => null,
                'technologies' => $case['technologies'],
            ]],
            'qualifications' => [],
            'skills' => [],
            'languages' => [],
            'others' => [],
        ]);

        $path = app(ResumeDocumentService::class)->generate($resume);

        Storage::disk('local')->assertExists($path);
    }
});
