<?php

use App\Models\ResumeAnalytic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// Opt-in only: run in the isolated SQLite test container, never against application data.
it('runs a complete native pipeline against a real local Ollama', function ($extension) {
    Storage::fake('local');
    Http::preventStrayRequests();
    Http::allowStrayRequests([rtrim(config('resume.ollama.url'), '/').'/api/chat']);
    $auth = actingAsUser();
    $started = microtime(true);
    $id = $this->withHeaders($auth['headers'])->postJson('/api/client/resumes/new-resume', [
        'resume_cv' => new UploadedFile(base_path('tests/Fixtures/documents/full_resume.'.$extension), 'cv.'.$extension, null, null, true),
        'skills' => [['name' => 'PHP', 'years' => 4]],
    ])->assertOk()->json('data.user_resume_id');
    $this->withHeaders($auth['headers'])->postJson('/api/client/services/resumes/process', ['user_resume_id' => $id])->assertOk();
    $analytic = ResumeAnalytic::where('user_resume_id', $id)->sole();
    expect($analytic->header['name'])->not->toBeEmpty()
        ->and(collect($analytic->skills)->first(fn ($skill) => mb_strtolower($skill['name']) === 'php')['years'])->toBe(4);
    $this->withHeaders($auth['headers'])->getJson('/api/client/resumes/pendings/'.$analytic->id)->assertOk();
    $this->withHeaders($auth['headers'])->postJson('/api/client/resumes/'.$id.'/finish', [
        'user_resume_id' => $id, 'header' => $analytic->header, 'experiences' => $analytic->experiences,
        'qualifications' => $analytic->qualifications, 'projects' => $analytic->projects,
        'skills' => $analytic->skills, 'languages' => $analytic->languages,
    ])->assertOk();
    $download = $this->withHeaders($auth['headers'])->get('/api/client/resumes/'.$id.'/download');
    $download->assertOk()->assertDownload('curriculo.pdf');
    expect($download->streamedContent())->toStartWith('%PDF-');
    fwrite(STDOUT, sprintf("\nNative %s flow: %.2f seconds, model %s\n", $extension, microtime(true) - $started, config('resume.ollama.model')));
})->with(['pdf', 'docx'])->skip(fn () => ! filter_var(getenv('RUN_OLLAMA_INTEGRATION'), FILTER_VALIDATE_BOOLEAN), 'Requires explicit local Ollama integration opt-in');
