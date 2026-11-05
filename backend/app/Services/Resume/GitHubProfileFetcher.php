<?php

namespace App\Services\Resume;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GitHubProfileFetcher
{
    private const RESERVED = ['orgs', 'organizations', 'settings', 'features', 'marketplace', 'sponsors', 'notifications', 'issues', 'pulls', 'topics', 'collections', 'trending', 'explore', 'about', 'pricing', 'contact', 'security', 'login', 'join', 'apps'];

    public function fetch(?string $url): ?array
    {
        $username = $this->username($url);
        if ($username === null) {
            return null;
        }
        $client = Http::baseUrl('https://api.github.com')->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'Bom-Curriculo'])
            ->connectTimeout(3)->timeout(config('resume.github.timeout_seconds'))
            ->withoutRedirecting();
        if ($token = config('resume.github.token')) {
            $client->withToken($token);
        }
        try {
            $profile = $client->get('/users/'.$username);
            if (! $profile->successful()) {
                return $this->unavailable('profile_http', $profile->status());
            }
            $repos = $client->get('/users/'.$username.'/repos', ['sort' => 'pushed', 'per_page' => 30]);
            if (! $repos->successful()) {
                return $this->unavailable('repos_http', $repos->status());
            }
        } catch (ConnectionException) {
            return $this->unavailable('connection');
        }
        $user = $profile->json();
        $repositories = $repos->json();
        if (! is_array($user) || ! is_string($user['login'] ?? null)
            || ! is_array($repositories) || ! array_is_list($repositories)) {
            return $this->unavailable('invalid_response');
        }
        $ownRepos = array_filter($repositories, fn ($repo) => is_array($repo)
            && empty($repo['fork']) && is_string($repo['name'] ?? null) && is_string($repo['html_url'] ?? null));
        usort($ownRepos, fn ($a, $b) => $this->count($b['stargazers_count'] ?? null) <=> $this->count($a['stargazers_count'] ?? null));

        return [
            'username' => $user['login'], 'name' => $this->text($user['name'] ?? null),
            'bio' => $this->text($user['bio'] ?? null), 'location' => $this->text($user['location'] ?? null),
            'blog' => $this->text($user['blog'] ?? null), 'public_repos' => $this->count($user['public_repos'] ?? null),
            'top_repos' => array_map(fn ($repo) => [
                'name' => $repo['name'], 'description' => $this->text($repo['description'] ?? null),
                'language' => $this->text($repo['language'] ?? null), 'url' => $repo['html_url'],
                'stars' => $this->count($repo['stargazers_count'] ?? null),
            ], array_slice($ownRepos, 0, 6)),
        ];
    }

    private function username(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $parts = parse_url(str_contains($url, '://') ? $url : 'https://'.$url);
        if ($parts === false || ! in_array(strtolower($parts['host'] ?? ''), ['github.com', 'www.github.com'], true)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }
        $username = explode('/', trim($parts['path'] ?? '', '/'))[0];

        return preg_match('/^[a-z0-9][a-z0-9-]{0,38}$/i', $username)
            && ! in_array(strtolower($username), self::RESERVED, true) ? $username : null;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function count(mixed $value): int
    {
        return is_int($value) ? max(0, $value) : 0;
    }

    private function unavailable(string $reason, ?int $status = null): null
    {
        Log::notice('GitHub enrichment unavailable.', ['reason' => $reason, 'status' => $status]);

        return null;
    }
}
