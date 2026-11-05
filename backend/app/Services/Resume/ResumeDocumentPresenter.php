<?php

namespace App\Services\Resume;

/** Non-destructive presentation: never rewrites stored facts or calls an LLM. */
final class ResumeDocumentPresenter
{
    public function present(array $resume): array
    {
        $portuguese = str_starts_with(strtolower(config('resume.output_language', 'pt-BR')), 'pt');
        $labels = $portuguese ? [
            'summary' => 'Resumo profissional', 'experiences' => 'Experiência profissional',
            'education' => 'Formação acadêmica', 'projects' => 'Projetos',
            'courses' => 'Cursos e certificações', 'skills' => 'Habilidades', 'languages' => 'Idiomas',
            'current' => 'Atual', 'studying' => 'Em andamento', 'technologies' => 'Tecnologias',
            'year' => 'ano', 'years' => 'anos',
        ] : [
            'summary' => 'Professional summary', 'experiences' => 'Professional experience',
            'education' => 'Education', 'projects' => 'Projects', 'courses' => 'Courses and certifications',
            'skills' => 'Skills', 'languages' => 'Languages', 'current' => 'Present',
            'studying' => 'In progress', 'technologies' => 'Technologies', 'year' => 'year', 'years' => 'years',
        ];
        $header = (array) ($resume['header'] ?? []);
        $contacts = $this->unique(array_merge(
            $this->strings($header['location'] ?? null),
            $this->strings($header['emails'] ?? null),
            $this->strings($header['email'] ?? null),
            $this->strings($header['contacts'] ?? null),
        ));
        $links = [];
        foreach (ResumeLinks::merge((array) ($header['links'] ?? [])) as $label => $url) {
            $label = match (mb_strtolower(trim((string) $label))) {
                'github' => 'GitHub', 'linkedin' => 'LinkedIn',
                'portfolio', 'portfólio' => $portuguese ? 'Portfólio' : 'Portfolio',
                default => $this->text($label),
            };
            $links[] = ['label' => $label, 'url' => trim($url)];
        }
        $groups = ['experiences' => [], 'education' => [], 'projects' => [], 'courses' => []];
        foreach ($resume['experiences'] ?? [] as $item) {
            $groups['experiences'][] = $this->entry(
                $item['role'] ?? null, $item['company'] ?? null,
                $this->join([$this->period($item, 'is_actual', $labels['current']), $this->join([$item['city'] ?? null, $item['state'] ?? null, $item['country'] ?? null], ', ')]),
                $item['description'] ?? null,
            );
        }
        foreach ($resume['qualifications'] ?? [] as $item) {
            $group = ($item['type'] ?? '') === 'extracurricular_course' ? 'courses' : 'education';
            $groups[$group][] = $this->entry($item['title'] ?? null, $item['institution'] ?? null, $this->period($item, 'is_coursing', $labels['studying']));
        }
        foreach ($resume['projects'] ?? [] as $item) {
            $groups['projects'][] = $this->entry(
                $item['title'] ?? null, null, $this->period($item), $item['description'] ?? null,
                $item['url'] ?? null, $this->join($this->strings($item['technologies'] ?? null), ', '),
            );
        }
        $certifications = $resume['others']['certifications'] ?? [];
        foreach (is_array($certifications) ? $certifications : [] as $item) {
            $certificate = is_string($item) ? ['title' => $item] : (array) $item;
            $entry = $this->entry(
                $certificate['title'] ?? $certificate['name'] ?? null,
                $certificate['institution'] ?? $certificate['issuer'] ?? null,
                $this->join([$certificate['completion_date'] ?? $certificate['date'] ?? $certificate['year'] ?? null, $certificate['credential_id'] ?? null]),
                $certificate['description'] ?? null, $certificate['url'] ?? null,
            );
            // Suppress only a certificate whose displayed facts already occur in a qualification.
            $duplicate = false;
            foreach (array_merge($groups['education'], $groups['courses']) as $qualification) {
                if ($entry['title'] !== '' && $this->sameFacts($entry, $qualification)) {
                    $duplicate = true;
                    break;
                }
            }
            if (! $duplicate) {
                $groups['courses'][] = $entry;
            }
        }
        $sections = [];
        foreach ($groups as $key => $entries) {
            $entries = array_values(array_unique(array_filter($entries, fn ($entry) => implode('', $entry) !== ''), SORT_REGULAR));
            if ($entries !== []) {
                $sections[] = ['title' => $labels[$key], 'items' => $entries];
            }
        }
        $skills = [];
        foreach ($resume['skills'] ?? [] as $skill) {
            $name = $this->text($skill['name'] ?? null);
            if ($name !== '') {
                $years = $skill['years'] ?? null;
                $skills[] = $name.(is_numeric($years) ? ' ('.$years.' '.($years == 1 ? $labels['year'] : $labels['years']).')' : '');
            }
        }
        $levels = $portuguese
            ? ['beginner' => 'Básico', 'intermediate' => 'Intermediário', 'advanced' => 'Avançado', 'fluent' => 'Fluente', 'native' => 'Nativo']
            : ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced', 'fluent' => 'Fluent', 'native' => 'Native'];
        $languages = [];
        foreach ($resume['languages'] ?? [] as $item) {
            $name = $this->text($item['language'] ?? null);
            $level = $this->text($item['level'] ?? null);
            if ($name !== '') {
                $languages[] = $this->join([$name, $levels[mb_strtolower($level)] ?? $level], ' — ');
            }
        }

        return [
            'lang' => $portuguese ? 'pt-BR' : 'en', 'labels' => $labels,
            'name' => $this->text($header['name'] ?? null), 'headline' => $this->text($header['headline'] ?? null),
            'summary' => $this->text($header['summary'] ?? null), 'contacts' => $contacts, 'links' => $links,
            'sections' => $sections, 'skills' => $this->unique($skills), 'languages' => $this->unique($languages),
        ];
    }

    private function entry(mixed $title, mixed $subtitle = null, mixed $meta = null, mixed $description = null, mixed $url = null, mixed $technologies = null): array
    {
        return array_map($this->text(...), compact('title', 'subtitle', 'meta', 'description', 'url', 'technologies'));
    }

    private function sameFacts(array $certificate, array $qualification): bool
    {
        foreach ($certificate as $key => $value) {
            $same = in_array($key, ['title', 'subtitle'], true)
                ? mb_strtolower($value) === mb_strtolower($qualification[$key])
                : $value === $qualification[$key];
            if ($value !== '' && ! $same) {
                return false;
            }
        }

        return true;
    }

    private function period(array $item, ?string $current = null, string $currentLabel = ''): string
    {
        $end = $current && ($item[$current] ?? false) ? $currentLabel : ($item['end'] ?? null);

        return $this->join([$item['start'] ?? null, $end], ' — ');
    }

    private function join(array $items, string $separator = ' · '): string
    {
        return implode($separator, array_filter(array_map($this->text(...), $items), fn ($item) => $item !== ''));
    }

    private function text(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function strings(mixed $value): array
    {
        return array_values(array_filter(array_map($this->text(...), is_array($value) ? $value : [$value]), fn ($item) => $item !== ''));
    }

    private function unique(array $items): array
    {
        $seen = [];

        return array_values(array_filter($items, function ($item) use (&$seen) {
            $key = mb_strtolower($item);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));
    }
}
