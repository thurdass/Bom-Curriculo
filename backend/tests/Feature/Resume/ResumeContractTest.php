<?php

use App\Models\ResumeAnalytic;
use App\Services\Resume\ResumeDocumentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['resume.ollama.url' => 'http://ollama.test']);
});

it('takes the canonical contract through processing, review and PDF without losing summary or score', function () {
    $auth = actingAsUser();
    Storage::put('cv.pdf', file_get_contents(base_path('tests/Fixtures/documents/full_resume.pdf')));
    $resume = $auth['user']->resumes()->create(['original_file_path_cv' => 'cv.pdf']);
    Http::fake([
        'http://ollama.test/api/chat' => Http::response([
            'model' => 'qwen3:4b', 'done' => true, 'done_reason' => 'stop',
            'message' => ['content' => file_get_contents(base_path('tests/Fixtures/generated_resume_payload.json'))],
        ]),
    ]);

    $this->withHeaders($auth['headers'])->postJson('/api/client/services/resumes/process', ['user_resume_id' => $resume->id])
        ->assertOk()->assertJsonPath('data.0.others.score', 88)
        ->assertJsonPath('data.0.header.summary', 'Desenvolvedora com experiência em APIs PHP.');

    $analytic = ResumeAnalytic::sole();
    $this->withHeaders($auth['headers'])->getJson('/api/client/resumes/pendings')
        ->assertJsonPath('data.0.skills.0.name', 'PHP');

    $this->withHeaders($auth['headers'])->postJson("/api/client/resumes/{$resume->id}/finish", [
        'user_resume_id' => $resume->id, 'header' => $analytic->header,
        'experiences' => [], 'skills' => $analytic->skills,
    ])->assertOk();

    Storage::assertExists($resume->fresh()->processed_file_path);
    expect($analytic->fresh()->experiences)->toBe([])
        ->and($analytic->fresh()->others['score'])->toBe(88)
        ->and($analytic->fresh()->ai_payload['experiences'])->toHaveCount(1);
});

it('projects stored payload-only analyses without overwriting deliberately reviewed empty lists', function () {
    $auth = actingAsUser();
    $payload = json_decode(file_get_contents(base_path('tests/Fixtures/generated_resume_payload.json')), true);
    $analytic = $auth['user']->resumeAnalytics()->create(['status' => 'success', 'ai_payload' => $payload]);
    $before = $analytic->fresh()->getAttributes();
    $this->withHeaders($auth['headers'])->getJson("/api/client/resumes/pendings/{$analytic->id}")
        ->assertJsonPath('data.header.name', 'Pessoa de Teste')
        ->assertJsonPath('data.others.score', 88);
    expect($analytic->fresh()->getAttributes())->toBe($before);
    $analytic->update(['skills' => []]);
    expect($analytic->fresh()->toArray()['skills'])->toBe([]);
});

it('rejects another users resume and mismatched finish IDs before rendering', function () {
    $auth = actingAsUser();
    $other = authUser();
    $resume = $other->resumes()->create();
    $other->resumeAnalytics()->create(['user_resume_id' => $resume->id, 'status' => 'success']);
    $this->mock(ResumeDocumentService::class)->shouldNotReceive('generate');
    $this->withHeaders($auth['headers'])->postJson("/api/client/resumes/{$resume->id}/finish", [
        'user_resume_id' => $resume->id, 'header' => [],
    ])->assertUnprocessable();

    $own = $auth['user']->resumes()->create();
    $auth['user']->resumeAnalytics()->create(['user_resume_id' => $own->id, 'status' => 'success']);
    $this->withHeaders($auth['headers'])->postJson("/api/client/resumes/{$resume->id}/finish", [
        'user_resume_id' => $own->id, 'header' => [],
    ])->assertUnprocessable();
});

it('renders legacy certifications in the PDF', function () {
    $auth = actingAsUser();
    $analytic = $auth['user']->resumeAnalytics()->create([
        'status' => 'success',
        'header' => ['name' => 'Example'],
        'experiences' => [], 'projects' => [], 'qualifications' => [], 'skills' => [], 'languages' => [],
        'others' => ['certifications' => [['title' => 'Cloud certification', 'issuer' => 'Example']]],
    ]);
    Storage::assertExists(app(ResumeDocumentService::class)->generate($analytic));
});
