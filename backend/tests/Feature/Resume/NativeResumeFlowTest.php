<?php

use App\Models\ResumeAnalytic;
use App\Models\UserResume;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    config(['resume.ollama.url' => 'http://ollama.test', 'resume.github.token' => null]);
});

it('uploads, extracts, enriches, generates, reviews and downloads without a document service', function ($extension) {
    $auth = actingAsUser();
    Http::fake([
        'https://api.github.com/users/example' => Http::response(['login' => 'example', 'bio' => 'Public profile']),
        'https://api.github.com/users/example/repos*' => Http::response([[
            'name' => 'api', 'html_url' => 'https://github.com/example/api', 'fork' => false, 'stargazers_count' => 2,
        ]]),
        'http://ollama.test/api/chat' => Http::response([
            'model' => 'qwen3:4b', 'done' => true, 'done_reason' => 'stop',
            'message' => ['content' => file_get_contents(base_path('tests/Fixtures/generated_resume_payload.json'))],
        ]),
    ]);
    $id = $this->withHeaders($auth['headers'])->postJson('/api/client/resumes/new-resume', [
        'resume_cv' => new UploadedFile(base_path('tests/Fixtures/documents/full_resume.'.$extension), 'cv.'.$extension, null, null, true),
        'resume_linkedin' => new UploadedFile(base_path('tests/Fixtures/documents/full_resume.pdf'), 'linkedin.pdf', null, null, true),
        'github_link' => 'https://github.com/example', 'site_link' => 'https://example.test',
        'skills' => [['name' => 'PHP', 'years' => 7]],
    ])->assertOk()->json('data.user_resume_id');
    $this->withHeaders($auth['headers'])->postJson('/api/client/services/resumes/process', ['user_resume_id' => $id])
        ->assertOk()->assertJsonPath('data.0.skills.0.years', 7);
    Http::assertSentCount(3);
    Http::assertSent(function ($request) {
        if ($request->url() !== 'http://ollama.test/api/chat') {
            return false;
        }
        $sources = json_decode($request['messages'][1]['content'], true);

        return str_contains($sources['resume_text'], 'Ana Exemplo')
            && str_contains($sources['linkedin_text'], 'Ana Exemplo')
            && $sources['github_profile']['top_repos'][0]['name'] === 'api';
    });
    $analytic = ResumeAnalytic::where('user_resume_id', $id)->sole();
    $this->withHeaders($auth['headers'])->postJson('/api/client/resumes/'.$id.'/finish', [
        'user_resume_id' => $id, 'header' => $analytic->header,
        'experiences' => $analytic->experiences, 'projects' => $analytic->projects,
        'qualifications' => $analytic->qualifications, 'skills' => $analytic->skills, 'languages' => $analytic->languages,
    ])->assertOk();
    $download = $this->withHeaders($auth['headers'])->get('/api/client/resumes/'.$id.'/download');
    $download->assertOk()->assertDownload('curriculo.pdf');
    expect($download->streamedContent())->toStartWith('%PDF-')
        ->and(UserResume::find($id)->status->value)->toBe('ready');
})->with(['pdf', 'docx']);
