<?php

use App\Services\Resume\OllamaClient;
use App\Services\Resume\ResumePrompt;
use Illuminate\Support\Facades\Http;

// A focused opt-in model regression, not a universal language or factuality detector.
it('translates repository prose without importing education dates into projects', function () {
    config(['resume.output_language' => 'pt-BR']);
    Http::preventStrayRequests();
    Http::allowStrayRequests([rtrim(config('resume.ollama.url'), '/').'/api/chat']);
    $sources = json_decode(file_get_contents(base_path('tests/Fixtures/ats_language_sources.json')), true);
    $payload = app(OllamaClient::class)->generate(app(ResumePrompt::class)->messages($sources));
    expect($payload['experiences'])->toBe([])->and($payload['projects'])->toHaveCount(2);
    foreach ($sources['github_profile']['top_repos'] as $repo) {
        $project = collect($payload['projects'])->firstWhere('title', $repo['name']);
        expect($project)->not->toBeNull();
        expect($project['start'])->toBeNull()->and($project['end'])->toBeNull()
            ->and($project['description'])->not->toBe($repo['description'])
            ->and($project['url'])->toBe($repo['url']);
    }
    expect($payload['qualifications'][0]['start'])->toBe('2025')
        ->and($payload['qualifications'][0]['end'])->toBeNull()
        ->and($payload['qualifications'][0]['is_coursing'])->toBeTrue();
})->skip(fn () => ! filter_var(getenv('RUN_OLLAMA_INTEGRATION'), FILTER_VALIDATE_BOOLEAN), 'Requires explicit local Ollama integration opt-in');
