<?php

namespace App\Services\Resume;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class OllamaClient
{
    public function generate(array $messages, ?string $resumeId = null): array
    {
        return $this->request($messages, ResumePayload::schema(), ResumePayload::validate(...), $resumeId, 'generation', ResumePrompt::VERSION);
    }

    public function score(array $messages, ?string $resumeId = null): array
    {
        return $this->request($messages, ResumeScorePayload::schema(), ResumeScorePayload::validate(...), $resumeId, 'score', ResumeScorePrompt::VERSION);
    }

    private function request(array $messages, array $schema, callable $validate, ?string $resumeId, string $operation, string $promptVersion): array
    {
        $config = config('resume.ollama');
        $started = hrtime(true);
        $context = [
            'request_id' => (string) Str::uuid(), 'provider' => 'ollama',
            'requested_model' => $config['model'], 'prompt_version' => $promptVersion,
            'operation' => $operation,
            'user_resume_id' => $resumeId,
        ];
        try {
            if (empty($config['url']) || empty($config['model']) || $config['num_predict'] < 1
                || $config['timeout_seconds'] < 1 || $config['num_ctx'] < 1) {
                throw new RuntimeException('Invalid Ollama configuration.', 503);
            }
            $response = Http::acceptJson()->asJson()->baseUrl(rtrim($config['url'], '/'))
                ->connectTimeout($config['connect_timeout_seconds'])->timeout($config['timeout_seconds'])
                ->post('/api/chat', [
                    'model' => $config['model'], 'messages' => $messages,
                    'format' => $schema, 'stream' => false, 'think' => false,
                    'keep_alive' => $config['keep_alive'],
                    'options' => ['temperature' => $config['temperature'], 'num_predict' => $config['num_predict'], 'num_ctx' => $config['num_ctx']],
                ]);
            if (! $response->successful()) {
                throw new RuntimeException($response->status() === 404
                    ? 'The configured Ollama model is unavailable.' : 'Ollama generation failed.', 503);
            }
            $body = $response->json();
            if (! is_array($body) || ($body['done'] ?? null) !== true
                || ($body['done_reason'] ?? null) !== 'stop'
                || ! is_string(data_get($body, 'message.content'))
                || ($body['model'] ?? null) !== $config['model']) {
                throw new RuntimeException('Ollama returned an incomplete or unexpected response.', 502);
            }
            $payload = $validate(JsonObject::decode($body['message']['content']));
            Log::info("Resume {$operation} completed.", $context + [
                'model' => $body['model'], 'duration_ms' => round((hrtime(true) - $started) / 1e6),
                'input_tokens' => is_int($body['prompt_eval_count'] ?? null) ? $body['prompt_eval_count'] : null,
                'output_tokens' => is_int($body['eval_count'] ?? null) ? $body['eval_count'] : null,
            ]);

            return $payload;
        } catch (Throwable $error) {
            Log::warning("Resume {$operation} failed.", $context + [
                'duration_ms' => round((hrtime(true) - $started) / 1e6), 'exception_type' => get_class($error),
            ]);
            if ($error instanceof ConnectionException) {
                throw new RuntimeException('Ollama is unavailable or generation timed out.', 503, $error);
            }
            // Do not propagate malformed model fields or HTTP response bodies to the client.
            throw new RuntimeException(
                $error->getCode() === 503 ? $error->getMessage() : 'Ollama returned an invalid resume.',
                $error->getCode() === 503 ? 503 : 502, $error,
            );
        }
    }
}
