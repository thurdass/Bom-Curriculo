<?php

namespace Tests\Support;

/** Synthetic data only; no personal documents belong in regression fixtures. */
final class ResumePdfProfiles
{
    public static function student(): array
    {
        return [
            'header' => [
                'name' => 'Ana Conceição de Oliveira', 'headline' => 'Estudante de Informática | Desenvolvimento Java',
                'location' => 'Salvador, Bahia', 'emails' => 'ana@example.test', 'email' => 'ana@example.test',
                'contacts' => '+55 00 00000-0000',
                'links' => ['Github' => 'https://github.com/example', 'GitHub' => 'https://github.com/example', 'LinkedIn' => 'https://linkedin.com/in/example'],
                'summary' => 'Estudante do curso técnico em Informática com foco em desenvolvimento de aplicações Java. Aplica conhecimentos de programação, testes e bancos de dados em projetos acadêmicos e pessoais.',
            ],
            'experiences' => [],
            'qualifications' => [['title' => 'Ensino Médio Integrado ao Técnico em Informática', 'institution' => 'Instituto Técnico de Exemplo', 'type' => 'technical_course', 'start' => '2025', 'end' => null, 'is_coursing' => true]],
            'projects' => array_map(fn ($i) => [
                'title' => 'Projeto acadêmico '.$i,
                'description' => 'Aplicação para organizar atividades e consultar informações. Implementa validação de dados, persistência e testes das principais funcionalidades.',
                'technologies' => ['Java', 'SQL'], 'url' => 'https://github.com/example/projeto-'.$i,
            ], range(1, 5)),
            'skills' => [['name' => 'Java', 'years' => 2], ['name' => 'SQL', 'years' => 1], ['name' => 'Git', 'years' => null]],
            'languages' => [['language' => 'Português', 'level' => 'native']],
            'others' => ['certifications' => [], 'score' => 90],
        ];
    }

    public static function experienced(): array
    {
        $data = self::student();
        $data['header']['headline'] = 'Engenheira de software';
        $data['header']['summary'] = 'Profissional com experiência em desenvolvimento e manutenção de sistemas de informação. Atua em análise de requisitos, testes automatizados e integração entre serviços.';
        $data['experiences'] = array_map(fn ($i) => [
            'role' => 'Engenheira de software '.$i, 'company' => 'Organização Exemplo '.$i,
            'start' => (string) (2010 + $i), 'end' => (string) (2011 + $i), 'is_actual' => $i === 6,
            'city' => 'Salvador', 'state' => null, 'country' => 'Brasil',
            'description' => 'Desenvolveu integrações entre sistemas internos, documentou requisitos e implementou testes automatizados. Colaborou com equipes de produto na revisão de processos, na análise de incidentes e na manutenção de serviços. Participou da revisão de código e do planejamento das entregas.',
        ], range(1, 6));
        $data['projects'] = array_slice($data['projects'], 0, 2);
        $data['qualifications'] = [['title' => 'Bacharelado em Sistemas de Informação', 'institution' => 'Universidade Exemplo', 'start' => '2006', 'end' => '2010', 'is_coursing' => false]];

        return $data;
    }

    public static function extensive(): array
    {
        $data = self::experienced();
        $data['projects'] = array_map(fn ($i) => [
            'title' => sprintf('Projeto documentado %02d', $i), 'start' => '2020', 'end' => '2021',
            'description' => 'Implementação de um serviço de consulta com validação, documentação e testes de integração. O projeto demonstra organização do código e persistência dos registros.',
            'url' => 'https://example.test/projetos/'.$i, 'technologies' => 'PHP, PostgreSQL',
        ], range(1, 24));
        foreach (range(1, 12) as $i) {
            $data['qualifications'][] = ['title' => sprintf('Curso complementar %02d', $i), 'institution' => 'Escola Exemplo', 'type' => 'extracurricular_course', 'end' => '2024'];
        }

        return $data;
    }

    public static function longEntry(): array
    {
        $data = self::student();
        $data['projects'] = [[
            'title' => 'Projeto com documentação extensa',
            'description' => implode("\n", array_map(fn ($i) => sprintf('Registro %03d: análise de requisitos, implementação e validação de funcionalidades documentadas no projeto.', $i), range(1, 100))),
            'url' => 'https://example.test/'.str_repeat('caminho-documentado-', 12),
        ]];

        return $data;
    }
}
