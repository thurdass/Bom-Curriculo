<?php

namespace App\Services\Resume;

use App\Models\User;
use App\Models\UserResume;
use App\Services\Bot\Actions\PublishBotAction;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PythonResumeGenerator implements ResumeGenerator
{
    public function generate(UserResume $resume, User $user): array
    {
        $config = config('services.bot');
        if (empty($config['url'])) {
            throw new RuntimeException('BOT_URL is not configured.');
        }
        $client = Http::acceptJson()->baseUrl(rtrim($config['url'], '/'))
            ->connectTimeout($config['connect_timeout_seconds']);
        $health = (clone $client)->timeout($config['health_timeout_seconds'])->get('/health')->throw();
        if ($health->json('status') !== 'online') {
            throw new RuntimeException('BOT OFFLINE', 503);
        }

        return (new PublishBotAction(
            $client->baseUrl(rtrim($config['url'], '/').'/api/v1')->timeout($config['timeout_seconds']),
            $user, $resume,
        ))->handle();
    }
}
