<?php

use App\Enums\UserResumeEnum;
use App\Models\ResumeAnalytic;
use App\Services\Resume\ResumeSourcePreparer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    config([

        'resume.ollama.url' => 'http://ollama.test', 'resume.ollama.model' => 'qwen3:4b',
    ]);
    $this->auth = actingAsUser();
    Storage::put('cv.pdf', 'PDF fixture');
    $this->resume = $this->auth['user']->resumes()->create(['original_file_path_cv' => 'cv.pdf']);
    $this->sources = [
        'version' => 1, 'resume_text' => 'Private CV text.', 'linkedin_text' => 'LinkedIn text.',
        'github_profile' => ['login' => 'example', 'repositories' => []],
        'github_url' => 'https://github.com/example', 'portfolio_url' => 'https://example.test',
        'additional_skills' => [['name' => 'php', 'years' => 7], ['name' => 'Rust', 'years' => null]],
    ];
    $this->preparationError = null;
    $this->mock(ResumeSourcePreparer::class)->shouldReceive('prepare')->andReturnUsing(function () {
        if ($this->preparationError !== null) {
            throw new RuntimeException('Document preparation failed.', $this->preparationError);
        }

        return $this->sources;
    });
    $this->completion = [
        'model' => 'qwen3:4b', 'done' => true, 'done_reason' => 'stop',
        'message' => ['role' => 'assistant', 'content' => file_get_contents(base_path('tests/Fixtures/generated_resume_payload.json'))],
        'prompt_eval_count' => 1024, 'eval_count' => 450,
    ];
});

function processOllamaResume($test)
{
    return $test->withHeaders($test->auth['headers'])->postJson('/api/client/services/resumes/process', ['user_resume_id' => $test->resume->id]);
}

it('persists a reported GitHub link only once when the model varies its label casing', function () {
    $payload = json_decode($this->completion['message']['content']);
    $payload->header->links = (object) ['Github' => 'https://github.com/guessed', 'github' => 'https://github.com/example'];
    $this->completion['message']['content'] = json_encode($payload);
    Http::fake(['*/api/chat' => Http::response($this->completion)]);
    processOllamaResume($this)->assertOk()
        ->assertJsonPath('data.0.header.links.GitHub', 'https://github.com/example')
        ->assertJsonCount(2, 'data.0.header.links');
    expect(ResumeAnalytic::sole()->ai_payload['header']['links'])->toBe([
        'GitHub' => 'https://github.com/example', 'Portfolio' => 'https://example.test',
    ]);
});

it('generates directly with explicit model, schema, sources and preserved user facts', function () {
    Log::spy();
    $options = [];
    Http::fake(function ($request, $requestOptions) use (&$options) {
        $options[$request->url()] = $requestOptions;

        return Http::response($this->completion);
    });
    processOllamaResume($this)->assertOk()->assertJsonPath('data.0.skills.0.years', 7)
        ->assertJsonPath('data.0.header.links.Portfolio', 'https://example.test')
        ->assertJsonPath('data.0.skills.1.name', 'Rust')->assertJsonPath('data.0.others.score', 88);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'http://ollama.test/api/chat'
        && $request['model'] === 'qwen3:4b' && $request['stream'] === false && $request['think'] === false
        && $request['format']['properties']['score']['maximum'] === 100
        && $request['options']['num_predict'] === 2048 && $request['options']['temperature'] === 0.2
        && json_decode($request['messages'][1]['content'], true) === $this->sources);
    expect($options['http://ollama.test/api/chat']['timeout'])->toEqual(240)
        ->and($this->resume->fresh()->status)->toBe(UserResumeEnum::ANALYZE);
    Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'Resume generation completed.'
        && $context['provider'] === 'ollama' && $context['model'] === 'qwen3:4b'
        && $context['input_tokens'] === 1024 && ! str_contains(json_encode($context), 'Private CV'));
});

it('rejects invalid completions without replacing an existing analysis or retrying', function (string $failure) {
    $existing = $this->auth['user']->resumeAnalytics()->create([
        'user_resume_id' => $this->resume->id, 'status' => 'success', 'header' => ['name' => 'Saved'],
    ]);
    $before = $existing->fresh()->getAttributes();
    switch ($failure) {
        case 'truncated': $this->completion['done_reason'] = 'length';
            break;
        case 'unfinished': $this->completion['done'] = false;
            break;
        case 'wrong model': $this->completion['model'] = 'different:8b';
            break;
        case 'invalid json': $this->completion['message']['content'] = '{partial';
            break;
        case 'list root': $this->completion['message']['content'] = '[]';
            break;
        case 'invalid schema': $this->completion['message']['content'] = '{"score":200}';
            break;
    }
    Http::fake([
        '*/api/chat' => Http::response($this->completion),
    ]);
    processOllamaResume($this)->assertStatus(502);
    Http::assertSentCount(1);
    expect($existing->fresh()->getAttributes())->toBe($before)
        ->and($this->resume->fresh()->status)->toBe(UserResumeEnum::FAIL);
})->with(['truncated', 'unfinished', 'wrong model', 'invalid json', 'list root', 'invalid schema']);

it('handles unavailable models and HTTP failures without exposing upstream data', function (int $status) {
    Http::fake([
        '*/api/chat' => Http::response(['error' => 'Private upstream content'], $status),
    ]);
    processOllamaResume($this)->assertStatus(503)->assertDontSee('Private upstream content');
    expect(ResumeAnalytic::count())->toBe(0);
    Http::assertSentCount(1);
})->with([404, 429, 500]);

it('handles timeouts without a second inference attempt', function () {
    Http::fake([
        '*/api/chat' => fn () => throw new ConnectionException('Private timeout detail'),
    ]);
    processOllamaResume($this)->assertStatus(503)->assertDontSee('Private timeout detail');
    expect(ResumeAnalytic::count())->toBe(0);
});

it('does not infer when preparation fails or exceeds the configured input limit', function (string $case) {
    if ($case === 'large') {
        config(['resume.ollama.max_source_characters' => 5]);
    }
    if ($case === 'document') {
        $this->preparationError = 422;
    }
    processOllamaResume($this)->assertStatus(422);
    Http::assertNothingSent();
})->with(['large', 'document']);

it('rejects concurrent generation without changing resume status and releases its own lock after failure', function () {
    $lock = Cache::lock('resume-generation:'.$this->resume->id, 330);
    $lock->get();
    processOllamaResume($this)->assertStatus(409);
    Http::assertNothingSent();
    expect($this->resume->fresh()->status)->toBe(UserResumeEnum::PENDING);
    $lock->release();
    $this->preparationError = 503;
    processOllamaResume($this)->assertStatus(503);
    $next = Cache::lock('resume-generation:'.$this->resume->id, 330);
    expect($next->get())->toBeTrue();
    $next->release();
});

it('checks ownership before preparation or inference', function () {
    $other = actingAsUser();
    $this->withHeaders($other['headers'])->postJson('/api/client/services/resumes/process', ['user_resume_id' => $this->resume->id])->assertNotFound();
    Http::assertNothingSent();
});
