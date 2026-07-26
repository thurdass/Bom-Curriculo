<?php

namespace App\Services\Bot;

use App\Helpers\ResponseData;
use App\Models\UserResume;
use App\Services\Bot\Actions\ProcessBotAction;
use App\Services\Bot\Actions\PublishBotAction;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProcessBotService
{
    public function __construct(
        private $http = null,
        public string $endpointBase = 'api/v1'
    ) {
        $config = config('services.bot');
        $baseUrl = rtrim($config['url'], '/');
        $endpointBase = ltrim(rtrim(trim($this->endpointBase), '/'), '/');

        $this->http = Http::acceptJson()->baseUrl($baseUrl);
        $this->checkHealth();

        $this->http = $this->http->baseUrl($baseUrl.'/'.$endpointBase);
    }

    protected function checkHealth()
    {
        $response = $this->http->get('/health');

        if ($response->status() !== 200 || ($response->json()['status'] ?? '') !== 'online') {
            throw new Exception('BOT OFFLINE', 503);
        }
    }

    public function analyse(Request $request)
    {
        try {

            $resume = UserResume::find($request->input('user_resume_id'));
            if ($resume && $request->user()->id !== $resume->user_id) {
                return ResponseData::success('Not found!', [
                    'message' => 'Resume not found for this user.',
                ], 404);
            }

            // Callback of BOT
            $callback = (new PublishBotAction(
                $this->http,
                $request->user(),
                $resume->toArray()
            )
            )::handle();

            // Call callback Process overflow
            $response = ProcessBotAction::handle($callback, $resume);

            if ($response === null) {
                throw new Exception('Error to generate callback!');
            }

            return ResponseData::success('Processed successfuly', [
                $response,
            ], 200);

        } catch (RequestException $exception) {

            $statusCode = $exception->response->status();
            $errorData = $exception->response->json();

            // Mapping Errors 402, 503
            // TODO: implements on future the mapping if the resume will be finished

            return ResponseData::error('Failed', [
                'message' => $errorData['detail'] ?? $exception->getMessage(),
                'details' => $errorData,
            ], $statusCode);

        } catch (Exception $exception) {

            $code = $exception->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;

            return ResponseData::error('Failed', [
                'message' => $exception->getMessage(),
            ], $statusCode);

        }
    }
}
