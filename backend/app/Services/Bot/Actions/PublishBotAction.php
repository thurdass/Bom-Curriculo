<?php

namespace App\Services\Bot\Actions;

use App\Models\User;
use App\Models\UserResume;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;

class PublishBotAction
{
    private static $http;

    private static User $user;

    private static UserResume|array $resume;

    public function __construct(
        $http,
        User $user,
        UserResume|array $resume
    ) {
        self::$http = $http;
        self::$user = $user;
        self::$resume = $resume;
    }

    public static function handle()
    {
        try {

            $resume = UserResume::find(self::$resume['id']);

            $user = self::$user;
            $bot = self::build($resume, $user);

            if ($bot->getStatusCode() !== 200) {
                // throw new Exception($bot->json()->detail, $bot->getStatusCode());

                // TODO: Remove all overflow validated
                return self::mockData();
            }

            return $bot->json();

        } catch (RequestException $exception) {
            $statusCode = $exception->response->status();
            throw new Exception($exception->getMessage(), $statusCode, $exception);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    private static function build($resume, $user)
    {
        $payload = self::dataToBot($resume, $user);

        if (isset($payload['resume_cv_stream']) && is_resource($payload['resume_cv_stream'])) {
            return self::$http->attach(
                'resume_cv',
                $payload['resume_cv_stream'],
                $payload['resume_cv_filename']
            )->post('/build', [
                'additional_skills' => json_encode($payload['additional_skills']),
                'github_url' => $payload['github_url'],
                'portfolio_url' => $payload['portfolio_url'],
                'resume_linkedin' => $payload['resume_linkedin'],
            ]); // ->throw();
        }

        return self::$http->post('/build', $payload);
    }

    private static function dataToBot($userResume, $user)
    {
        $expires_link = now()->addDay();
        $resume_cv_stream = null;
        $resume_cv_filename = 'resume.pdf';
        $resume_linkedin = null;

        $cv_path = null;
        if ($userResume && $userResume->id !== null) {
            $cv_path = $userResume->original_file_path_cv;
            if (! empty($userResume->original_file_path_linkedin)) {
                $resume_linkedin = Storage::temporaryUrl($userResume->original_file_path_linkedin, $expires_link);
            }
        } else {
            $cv_path = $user->resume_cv;
            if (! empty($user->resume_linkedin)) {
                $resume_linkedin = Storage::temporaryUrl($user->resume_linkedin, $expires_link);
            }
        }

        if (! empty($cv_path) && Storage::exists($cv_path)) {
            $resume_cv_stream = Storage::readStream($cv_path);
            $resume_cv_filename = basename($cv_path);
        }

        return [
            'additional_skills' => $user->skills()->select('name', 'years')->get()->toArray(),
            'github_url' => $user->github_link,
            'portfolio_url' => $user->site_link,
            'resume_cv_stream' => $resume_cv_stream,
            'resume_cv_filename' => $resume_cv_filename,
            'resume_linkedin' => $resume_linkedin,
        ];
    }

    private static function mockData()
    {
        return [
            'experiences' => [
                [
                    'city' => 'São Paulo',
                    'company' => 'Tech Solutions Inovações',
                    'country' => 'Brasil',
                    'description' => 'Desenvolvimento de APIs RESTful e integração de microsserviços de alta escala.',
                    'end' => '2025-12',
                    'is_actual' => false,
                    'role' => 'Desenvolvedor Backend Pleno',
                    'start' => '2023-01',
                    'state' => 'SP',
                ],
                [
                    'city' => 'Campinas',
                    'company' => 'Global Logística S.A.',
                    'country' => 'Brasil',
                    'description' => 'Manutenção de sistemas legados e refatoração de banco de dados relacionais.',
                    'end' => null,
                    'is_actual' => true,
                    'role' => 'Analista de Sistemas Senior',
                    'start' => '2026-01',
                    'state' => 'SP',
                ],
            ],
            'header' => [
                'contacts' => ['+55 11 99999-8888'],
                'email' => 'silva.junior@provedor.com',
                'emails' => ['silva.junior@provedor.com', 'contato.junior@dev.io'],
                'headline' => 'Engenheiro de Software focado em PHP e Arquitetura de Nuvem',
                'links' => [
                    'linkedin' => 'https://linkedin.com',
                    'github' => 'https://github.com',
                    'portfolio' => 'https://meusite.dev',
                ],
                'location' => 'São Paulo, SP - Brasil',
                'name' => 'Carlos Silva Júnior',
            ],
            'languages' => [
                [
                    'language' => 'Inglês',
                    'level' => 'advanced',
                ],
                [
                    'language' => 'Espanhol',
                    'level' => 'intermediate',
                ],
            ],
            'others' => [
                'certifications' => ['AWS Certified Cloud Practitioner', 'Laravel Certified Developer'],
            ],
            'professional_summary' => 'Profissional com mais de 5 anos de experiência no ecossistema PHP. Especialista na criação de soluções escaláveis utilizando Laravel, Docker e arquitetura de microsserviços. Comprometido com a qualidade de código e testes automatizados.',
            'projects' => [
                [
                    'description' => 'Plataforma completa de comércio eletrônico com pagamentos recorrentes e integração de estoque via API.',
                    'end' => '2024-06',
                    'start' => '2024-01',
                    'technologies' => ['Laravel', 'Vue.js', 'Redis', 'PostgreSQL'],
                    'title' => 'E-Commerce Engine X',
                    'url' => 'https://github.com/ecommerce-engine',
                ],
            ],
            'qualifications' => [
                [
                    'end' => '2022-12',
                    'institution' => 'Universidade de Tecnologia do Estado',
                    'is_coursing' => false,
                    'start' => '2019-01',
                    'title' => 'Bacharelado em Ciência da Computação',
                    'type' => 'higher_education',
                ],
            ],
            'score' => 85,
            'skills' => [
                [
                    'name' => 'PHP / Laravel',
                    'years' => 5,
                ],
                [
                    'name' => 'Docker',
                    'years' => 3,
                ],
                [
                    'name' => 'MySQL',
                    'years' => 5,
                ],
                [
                    'name' => 'Git / GitHub Actions',
                    'years' => 4,
                ],
            ],
        ];
    }
}
