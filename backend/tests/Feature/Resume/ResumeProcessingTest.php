<?php

use App\Models\ResumeAnalytic;
use App\Services\Resume\JsonObject;
use App\Services\Resume\ResumeGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
});

it('persists canonical and legacy payloads and replaces a previous analysis atomically', function ($fixture) {
    $auth = actingAsUser();
    $resume = $auth['user']->resumes()->create();
    $payload = JsonObject::decode(file_get_contents(base_path('tests/Fixtures/'.$fixture)));
    $this->mock(ResumeGenerator::class)->shouldReceive('generate')->twice()->andReturn($payload);
    foreach (['resumes', 'bot'] as $alias) {
        $this->withHeaders($auth['headers'])->postJson('/api/client/services/'.$alias.'/process', ['user_resume_id' => $resume->id])->assertOk();
    }
    $analytic = ResumeAnalytic::sole();
    expect($analytic->user_id)->toBe($auth['user']->id)->and($analytic->user_resume_id)->toBe($resume->id)
        ->and($analytic->ai_payload)->toBe(json_decode(json_encode($payload), true))
        ->and($analytic->header['summary'])->toBe($payload['professional_summary'])
        ->and($resume->fresh()->status->value)->toBe('analyze');
})->with(['generated_resume_payload.json', 'legacy_resume_payload.json']);

it('does not overwrite an existing analysis when generation or validation fails', function ($failure) {
    $auth = actingAsUser();
    $resume = $auth['user']->resumes()->create();
    $analytic = $auth['user']->resumeAnalytics()->create([
        'user_resume_id' => $resume->id, 'status' => 'success', 'header' => ['name' => 'Saved'],
    ]);
    $before = $analytic->fresh()->getAttributes();
    $mock = $this->mock(ResumeGenerator::class)->shouldReceive('generate')->once();
    if ($failure === 'exception') {
        $mock->andThrow(new RuntimeException('Ollama unavailable.', 503));
    } else {
        $mock->andReturn(['header' => [], 'score' => 101]);
    }
    $this->withHeaders($auth['headers'])->postJson('/api/client/services/resumes/process', ['user_resume_id' => $resume->id])
        ->assertStatus($failure === 'exception' ? 503 : 502);
    expect($analytic->fresh()->getAttributes())->toBe($before)->and($resume->fresh()->status->value)->toBe('fail');
})->with(['exception', 'schema']);

it('rejects absent or invalid IDs before generation', function ($id) {
    $auth = actingAsUser();
    $this->mock(ResumeGenerator::class)->shouldNotReceive('generate');
    $this->withHeaders($auth['headers'])->postJson('/api/client/services/resumes/process', ['user_resume_id' => $id])->assertUnprocessable();
})->with([null, '', 'not-uuid', 1]);

it('rejects unknown and other user resumes before generation', function ($otherOwner) {
    $auth = actingAsUser();
    $id = $otherOwner ? authUser()->resumes()->create()->id : (string) Str::uuid();
    $this->mock(ResumeGenerator::class)->shouldNotReceive('generate');
    $this->withHeaders($auth['headers'])->postJson('/api/client/services/resumes/process', ['user_resume_id' => $id])->assertNotFound();
})->with([true, false]);

it('rejects missing or unreadable documents before inference', function ($path, $content) {
    $auth = actingAsUser();
    $resume = $auth['user']->resumes()->create(['original_file_path_cv' => $path]);
    if ($content !== null) {
        Storage::put($path, $content);
    }
    $this->withHeaders($auth['headers'])->postJson('/api/client/services/resumes/process', ['user_resume_id' => $resume->id])->assertUnprocessable();
    Http::assertNothingSent();
    expect(ResumeAnalytic::count())->toBe(0)->and($resume->fresh()->status->value)->toBe('fail');
})->with([[null, null], ['missing.pdf', null], ['invalid.pdf', 'broken'], ['invalid.docx', 'broken']]);
