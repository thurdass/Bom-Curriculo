<?php

use App\Services\Resume\Documents\DocumentTextExtractor;
use App\Services\Resume\ResumeSourcePreparer;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->text = 'Profissional de tecnologia com experiência em desenvolvimento de APIs, análise de requisitos e manutenção de sistemas. Formação em Ciência da Computação, conhecimentos de PHP, SQL, testes automatizados e colaboração em equipes de produto.';
});

it('prepares every source locally and keeps snapshot facts ahead of profile edits', function () {
    $user = authUser(['github_link' => 'https://github.com/changed']);
    $resume = $user->resumes()->create([
        'original_file_path_cv' => 'cv.docx', 'original_file_path_linkedin' => 'linkedin.pdf',
        'source_context' => ['github_url' => 'https://github.com/original', 'portfolio_url' => 'https://example.test', 'additional_skills' => [['name' => 'PHP', 'years' => 3]]],
    ]);
    $this->mock(DocumentTextExtractor::class, function ($mock) {
        $mock->shouldReceive('extract')->with('cv.docx')->once()->andReturn($this->text);
        $mock->shouldReceive('extract')->with('linkedin.pdf', true)->once()->andReturn('LinkedIn supporting text');
    });
    Http::fake([
        '*/users/original' => Http::response(['login' => 'original']),
        '*/users/original/repos*' => Http::response([]),
    ]);
    $sources = app(ResumeSourcePreparer::class)->prepare($resume, $user);
    expect($sources['version'])->toBe(1)->and($sources['resume_text'])->toBe($this->text)
        ->and($sources['linkedin_text'])->toBe('LinkedIn supporting text')
        ->and($sources['github_profile']['username'])->toBe('original')
        ->and($sources['portfolio_url'])->toBe('https://example.test')
        ->and($sources['additional_skills'])->toBe([['name' => 'PHP', 'years' => 3]]);
    Http::assertSentCount(2);
});

it('preserves profile fallback for uploads created before snapshots', function () {
    $user = authUser();
    $user->skills()->create(['name' => 'PHP', 'years' => null]);
    $resume = $user->resumes()->create(['original_file_path_cv' => 'cv.pdf']);
    $this->mock(DocumentTextExtractor::class)->shouldReceive('extract')->once()->andReturn($this->text);
    $sources = app(ResumeSourcePreparer::class)->prepare($resume, $user);
    expect($sources['additional_skills'])->toBe([['name' => 'PHP', 'years' => null]])
        ->and($sources['linkedin_text'])->toBeNull()->and($sources['github_profile'])->toBeNull();
    Http::assertNothingSent();
});

it('validates base CV before optional enrichment', function ($text, $reason) {
    $user = authUser(['github_link' => 'https://github.com/example']);
    $resume = $user->resumes()->create(['original_file_path_cv' => 'cv.pdf']);
    $this->mock(DocumentTextExtractor::class)->shouldReceive('extract')->andReturn($text);
    expect(fn () => app(ResumeSourcePreparer::class)->prepare($resume, $user))->toThrow(RuntimeException::class, $reason);
    Http::assertNothingSent();
})->with([['', 'empty'], ['Short', 'too_short'], [str_repeat('test ', 100), 'low_content_diversity']]);

it('continues without GitHub when unavailable but preserves the supplied link', function () {
    $user = authUser(['github_link' => 'https://github.com/example']);
    $resume = $user->resumes()->create(['original_file_path_cv' => 'cv.pdf']);
    $this->mock(DocumentTextExtractor::class)->shouldReceive('extract')->andReturn($this->text);
    Http::fake(['*' => Http::response([], 429)]);
    $sources = app(ResumeSourcePreparer::class)->prepare($resume, $user);
    expect($sources['github_profile'])->toBeNull()->and($sources['github_url'])->toBe('https://github.com/example');
});
