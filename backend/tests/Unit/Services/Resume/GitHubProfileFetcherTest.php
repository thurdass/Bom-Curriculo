<?php

use App\Services\Resume\GitHubProfileFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['resume.github.token' => null]);
});

it('fetches public profile and top six own repositories in star order', function () {
    $repos = array_map(fn ($i) => [
        'name' => 'project-'.$i, 'html_url' => 'https://github.com/example/project-'.$i,
        'stargazers_count' => $i, 'fork' => false, 'language' => 'PHP',
    ], range(1, 8));
    $repos[] = ['name' => 'fork', 'html_url' => 'https://github.com/example/fork', 'fork' => true, 'stargazers_count' => 999];
    config(['resume.github.token' => 'test-token']);
    Http::fake([
        'https://api.github.com/users/example' => Http::response(['login' => 'example', 'name' => 'Example', 'bio' => 'Bio', 'public_repos' => 9]),
        'https://api.github.com/users/example/repos*' => Http::response($repos),
    ]);
    $profile = app(GitHubProfileFetcher::class)->fetch('https://github.com/example');
    expect($profile['username'])->toBe('example')->and($profile['name'])->toBe('Example')
        ->and($profile['top_repos'])->toHaveCount(6)
        ->and(array_column($profile['top_repos'], 'stars'))->toBe([8, 7, 6, 5, 4, 3]);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->hasHeader('X-GitHub-Api-Version', '2022-11-28'));
});

it('never requests arbitrary hosts or reserved GitHub sections', function ($url) {
    expect(app(GitHubProfileFetcher::class)->fetch($url))->toBeNull();
    Http::assertNothingSent();
})->with([null, '', 'https://example.com/user', 'https://evilgithub.com/user', 'https://evil.test/github.com/user', 'https://github.com@evil.test/user', 'https://github.com/orgs/team', 'https://github.com/login', 'http://127.0.0.1/user', 'https://github.com:8080/user']);

it('continues without enrichment on HTTP errors including redirects', function ($status) {
    Http::fake(['*' => Http::response(['message' => 'upstream error'], $status, ['Location' => 'http://private.test'])]);
    expect(app(GitHubProfileFetcher::class)->fetch('https://github.com/example'))->toBeNull();
    Http::assertSentCount(1);
})->with([301, 401, 403, 404, 429, 500]);

it('continues without enrichment on connection failures', function () {
    Http::fake(fn () => throw new ConnectionException('upstream unavailable'));
    expect(app(GitHubProfileFetcher::class)->fetch('https://github.com/example'))->toBeNull();
});

it('rejects malformed JSON without interrupting resume generation', function () {
    Http::fake(['*' => Http::response('invalid json', 200)]);
    expect(app(GitHubProfileFetcher::class)->fetch('github.com/example'))->toBeNull();
});

it('ignores malformed repositories and treats repository endpoint failure as optional', function () {
    Http::fake([
        '*/users/example' => Http::response(['login' => 'example']),
        '*/repos*' => Http::response(['detail' => 'unavailable'], 503),
    ]);
    expect(app(GitHubProfileFetcher::class)->fetch('https://github.com/example'))->toBeNull();
});
