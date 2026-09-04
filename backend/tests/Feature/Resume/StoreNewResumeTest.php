<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads cv and linkedin files and creates a resume record', function () {

    Storage::fake('local');

    $auth = actingAsUser();

    $response = $this
        ->withHeaders($auth['headers'])
        ->postJson('/api/client/resumes/new-resume', [
            'resume_cv' => UploadedFile::fake()->create('cv.pdf', 100),
            'resume_linkedin' => UploadedFile::fake()->create('linkedin.pdf', 100),
            'github_link' => 'https://github.com/pedroaruana',
            'site_link' => 'https://pedroaruana.dev',
        ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'User Updated',
        ]);

    $user = $auth['user']->fresh();
    expect($user->github_link)->toBe('https://github.com/pedroaruana')
        ->and($user->site_link)->toBe('https://pedroaruana.dev')
        ->and($user->resume_cv)->not->toBeNull()
        ->and($user->resume_linkedin)->not->toBeNull();

    Storage::disk('local')->assertExists($user->resume_cv);

    $this->assertDatabaseHas('user_resumes', [
        'user_id' => $user->id,
    ]);
});

it('fails with only links, no files', function () {

    $auth = actingAsUser();

    $response = $this
        ->withHeaders($auth['headers'])
        ->postJson('/api/client/resumes/new-resume', [
            'github_link' => 'https://github.com/pedroaruana',
        ]);

    $response->assertClientError();

    $this->assertDatabaseMissing('user_resumes', [
        'user_id' => $auth['user']->id,
    ]);
});

it('accepts a DOCX CV supported by the bot', function () {

    Storage::fake('local');

    $auth = actingAsUser();

    $this->withHeaders($auth['headers'])
        ->postJson('/api/client/resumes/new-resume', [
            'resume_cv' => UploadedFile::fake()->create(
                'cv.docx',
                100,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ),
        ])
        ->assertOk();

    expect($auth['user']->resumes()->count())->toBe(1);
});

it('rejects a CV format unsupported by the bot', function () {

    Storage::fake('local');

    $auth = actingAsUser();

    $this->withHeaders($auth['headers'])
        ->postJson('/api/client/resumes/new-resume', [
            'resume_cv' => UploadedFile::fake()->create('cv.doc', 100, 'application/msword'),
        ])
        ->assertUnprocessable();

    expect($auth['user']->resumes()->count())->toBe(0);
});

it('rejects a LinkedIn format unsupported by the bot', function () {

    Storage::fake('local');

    $auth = actingAsUser();

    $this->withHeaders($auth['headers'])
        ->postJson('/api/client/resumes/new-resume', [
            'resume_cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
            'resume_linkedin' => UploadedFile::fake()->create(
                'linkedin.docx',
                100,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ),
        ])
        ->assertUnprocessable();

    expect($auth['user']->resumes()->count())->toBe(0);
});

it('requires authentication', function () {

    $this->postJson('/api/client/resumes/new-resume')
        ->assertUnauthorized();
});
