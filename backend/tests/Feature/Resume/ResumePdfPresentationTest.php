<?php

use App\Models\ResumeAnalytic;
use App\Services\Resume\ResumeDocumentService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\Support\ResumePdfProfiles;

beforeEach(function () {
    Storage::fake('local');
    config(['resume.output_language' => 'pt-BR']);
});

function renderResumePdf(array $data): array
{
    $resume = new ResumeAnalytic;
    $resume->forceFill($data);
    $before = $resume->toArray();
    $path = app(ResumeDocumentService::class)->generate($resume);
    expect($resume->toArray())->toBe($before);
    $process = new Process(['/usr/bin/pdftotext', '-layout', Storage::path($path), '-']);
    $process->mustRun();
    $text = $process->getOutput();

    return [$text, array_values(array_filter(explode("\f", $text), fn ($page) => trim($page) !== '')), Storage::path($path)];
}

it('fits a student with five projects on one legible page without empty or repeated sections', function () {
    [$text, $pages] = renderResumePdf(ResumePdfProfiles::student());
    expect($pages)->toHaveCount(1);
    expect($text)->toContain('Ana Conceição de Oliveira', 'Em andamento', 'Java (2 anos)', 'SQL (1 ano)', 'Português — Nativo')
        ->not->toContain('Sem experiência', 'Experiência profissional', 'Cursos e certificações', 'Current', ' years', 'Native', '90');
    expect(substr_count($text, 'Projetos'))->toBe(1)
        ->and(substr_count($text, 'https://github.com/example'))->toBe(6)
        ->and(substr_count($text, 'ana@example.test'))->toBe(1);
    foreach (range(1, 5) as $i) {
        expect(substr_count($text, 'Projeto acadêmico '.$i))->toBe(1);
    }
});

it('preserves all entries across pages without orphaned section headings', function ($profile) {
    $data = ResumePdfProfiles::$profile();
    [$text, $pages] = renderResumePdf($data);
    expect(count($pages))->toBeGreaterThan(1);
    foreach (array_merge($data['experiences'], $data['projects'], $data['qualifications']) as $item) {
        expect($text)->toContain($item['title'] ?? $item['role']);
    }
    foreach (['Experiência profissional', 'Formação acadêmica', 'Projetos', 'Habilidades', 'Idiomas'] as $heading) {
        expect(substr_count($text, $heading))->toBe(1);
        foreach ($pages as $page) {
            expect(trim($page))->not->toEndWith($heading);
        }
    }
    foreach ($pages as $page) {
        expect(mb_strlen(trim($page)))->toBeGreaterThan(100);
    }
})->with(['experienced', 'extensive']);

it('splits an oversized entry without losing lines or overflowing page margins', function () {
    [$text, $pages, $path] = renderResumePdf(ResumePdfProfiles::longEntry());
    expect(count($pages))->toBeGreaterThan(2);
    foreach (range(1, 100) as $i) {
        expect(substr_count($text, sprintf('Registro %03d:', $i)))->toBe(1);
    }
    $process = new Process(['/usr/bin/pdftotext', '-bbox', $path, '-']);
    $process->mustRun();
    $xml = simplexml_load_string($process->getOutput());
    $xml->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
    $words = $xml->xpath('//x:word');
    expect($words)->not->toBeEmpty();
    foreach ($words as $word) {
        expect((float) $word['xMin'])->toBeGreaterThanOrEqual(44)
            ->and((float) $word['xMax'])->toBeLessThanOrEqual(552)
            ->and((float) $word['yMax'])->toBeLessThanOrEqual(804);
    }
});
