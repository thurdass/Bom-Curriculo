<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('downloads the existing PDF as an authenticated attachment', function () {
    $auth = actingAsUser();
    $contents = '%PDF-1.4 test download';
    Storage::put('resumes/generated/test.pdf', $contents);
    $resume = $auth['user']->resumes()->create([
        'status' => 'ready', 'processed_file_path' => 'resumes/generated/test.pdf',
    ]);

    $response = $this->withHeaders($auth['headers'])->get('/api/client/resumes/'.$resume->id.'/download');
    $response->assertOk()->assertDownload('curriculo.pdf')->assertHeader('Content-Type', 'application/pdf');
    expect($response->streamedContent())->toBe($contents);
    Storage::assertExists('resumes/generated/test.pdf');
});

it('does not let another user download the PDF', function () {
    $owner = authUser();
    Storage::put('private.pdf', '%PDF-1.4 private');
    $resume = $owner->resumes()->create(['processed_file_path' => 'private.pdf']);
    $other = actingAsUser();
    $this->withHeaders($other['headers'])->getJson('/api/client/resumes/'.$resume->id.'/download')->assertNotFound();
});

it('requires authentication to download', function () {
    $resume = authUser()->resumes()->create();
    $this->getJson('/api/client/resumes/'.$resume->id.'/download')->assertUnauthorized();
});

it('returns not found for an ungenerated or missing PDF', function ($path) {
    $auth = actingAsUser();
    $resume = $auth['user']->resumes()->create(['processed_file_path' => $path]);
    $this->withHeaders($auth['headers'])->getJson('/api/client/resumes/'.$resume->id.'/download')->assertNotFound();
})->with([null, 'missing.pdf']);
