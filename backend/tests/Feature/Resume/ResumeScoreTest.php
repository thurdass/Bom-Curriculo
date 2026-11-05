<?php

use App\Models\ResumeAnalytic;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    config(['resume.ollama.url' => 'http://ollama.test']);
    $this->auth = actingAsUser();
    Storage::put('cv.pdf', file_get_contents(base_path('tests/Fixtures/documents/full_resume.pdf')));
    $this->resume = $this->auth['user']->resumes()->create(['original_file_path_cv' => 'cv.pdf', 'status' => 'ready']);
});

it('scores the original resume without rewriting or changing stored analysis', function () {
    Http::fake(['http://ollama.test/api/chat' => Http::response([
        'model' => 'qwen3:4b', 'done' => true, 'done_reason' => 'stop',
        'message' => ['content' => '{"score":80,"suggestion":"Inclua métricas verificáveis nas experiências."}'],
    ])]);
    $this->withHeaders($this->auth['headers'])->postJson('/api/client/resumes/'.$this->resume->id.'/score')
        ->assertOk()->assertJsonPath('data.score', 80)->assertJsonPath('data.suggestion', 'Inclua métricas verificáveis nas experiências.');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['format']['required'] === ['score', 'suggestion']
        && str_contains($request['messages'][0]['content'], 'exactly as given')
        && str_contains($request['messages'][1]['content'], 'Ana Exemplo'));
    expect(ResumeAnalytic::count())->toBe(0)->and($this->resume->fresh()->status->value)->toBe('ready');
});

it('validates the score response and leaves resume status unchanged on failure', function ($content) {
    Http::fake(['http://ollama.test/api/chat' => Http::response([
        'model' => 'qwen3:4b', 'done' => true, 'done_reason' => 'stop', 'message' => ['content' => $content],
    ])]);
    $this->withHeaders($this->auth['headers'])->postJson('/api/client/resumes/'.$this->resume->id.'/score')->assertStatus(502);
    expect($this->resume->fresh()->status->value)->toBe('ready');
})->with(['{"score":101,"suggestion":"x"}', '{"score":"80","suggestion":"x"}', '{"score":80,"suggestion":""}', '{"score":80}', '{"score":80,"suggestion":"x","extra":true}']);

it('authorizes scoring before extraction or inference', function () {
    $other = actingAsUser();
    $this->withHeaders($other['headers'])->postJson('/api/client/resumes/'.$this->resume->id.'/score')->assertNotFound();
    Http::assertNothingSent();
});
