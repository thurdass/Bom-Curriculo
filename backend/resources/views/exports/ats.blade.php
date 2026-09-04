<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>{{ $resume->header['name'] ?? 'Currículo' }}</title>

    <style>

        body{
            font-family: Arial, Helvetica, sans-serif;
            font-size:12px;
            color:#222;
            margin:40px;
            line-height:1.4;
        }

        h1{
            margin:0;
            font-size:26px;
        }

        h2{
            font-size:18px;
            margin-top:30px;
            margin-bottom:10px;
            border-bottom:1px solid #DDD;
            padding-bottom:4px;
        }

        h3{
            margin:10px 0 2px;
            font-size:14px;
        }

        p{
            margin:4px 0;
        }

        .muted{
            color:#666;
        }

        .section{
            margin-top:25px;
        }

        .item{
            margin-bottom:18px;
        }

        ul{
            padding-left:18px;
            margin-top:5px;
        }

        .skills span{
            display:inline-block;
            border:1px solid #AAA;
            padding:4px 8px;
            margin:3px;
            border-radius:4px;
            font-size:11px;
        }

        .contacts p{
            margin:2px 0;
        }

        hr{
            border:none;
            border-top:1px solid #DDD;
            margin:15px 0;
        }

    </style>

</head>

<body>

    {{-- HEADER --}}

    <h1>{{ $resume->header['name'] ?? '' }}</h1>

    <p>
        {{ $resume->header['headline'] ?? '' }}
    </p>

    <div class="contacts">

        <p>{{ $resume->header['location'] ?? '' }}</p>


        @php
            $emails = $resume->header['emails'] ?? null;
            $contacts = $resume->header['contacts'] ?? null;
        @endphp

        <p>{{ is_array($emails) ? implode(' / ', $emails) : ($emails ?? '') }}</p>
        <p>{{ is_array($contacts) ? implode(' / ', $contacts) : ($contacts ?? '') }}</p>

        @if(isset($resume->header['links']))

            @foreach($resume->header['links'] as $label => $link)

                <p>{{ ucfirst($label) }}: {{ $link }}</p>

            @endforeach

        @endif

    </div>

    {{-- SUMMARY --}}

    <div class="section">

        <h2>Resumo Profissional</h2>

        <p>

            {{ $resume->header['summary'] ?? '' }}

        </p>

    </div>

    {{-- EXPERIENCES --}}

    <div class="section">

        <h2>Experiência Profissional</h2>

        @forelse($resume->experiences as $experience)

            <div class="item">

                <h3>

                    {{ $experience['role'] }}

                </h3>

                <strong>

                    {{ $experience['company'] }}

                </strong>

                <p class="muted">

                    {{ $experience['city'] }},
                    {{ $experience['state'] }},
                    {{ $experience['country'] }}

                </p>

                <p class="muted">

                    {{ $experience['start'] }}
                    -

                    {{ $experience['is_actual'] ? 'Current' : $experience['end'] }}

                </p>

                <p>

                    {{ $experience['description'] }}

                </p>

            </div>

        @empty

            <p>Sem experiência.</p>

        @endforelse

    </div>

    @forelse($resume->projects as $project)


    {{-- PROJECTS --}}

    <div class="section">

        <h2>Projetos</h2>

        
            <div class="item">

                <h3>

                    {{ $project['title'] }}

                </h3>

                <p>

                    {{ $project['description'] }}

                </p>

                @if(!empty($project['url']))

                    <p>

                        {{ $project['url'] }}

                    </p>

                @endif

                @if(!empty($project['technologies']))

                    <p>

                        <strong>Técnologias:</strong>

                        {{ is_array($project['technologies']) ? implode(', ', $project['technologies']) : $project['technologies'] }}

                    </p>

                @endif

            </div>

        

    </div>

    @empty

    @endforelse

    {{-- EDUCATION --}}

    <div class="section">

        <h2>Qualificações</h2>

        @forelse($resume->qualifications as $qualification)

            <div class="item">

                <h3>

                    {{ $qualification['title'] }}

                </h3>

                <p>

                    {{ $qualification['institution'] }}

                </p>

                <p>

                    {{ $qualification['start'] }}

                    -

                    {{ $qualification['is_coursing'] ? 'Current' : $qualification['end'] }}

                </p>

            </div>

        @empty

            <p>Sem qualificações.</p>

        @endforelse

    </div>

    {{-- SKILLS --}}

    <div class="section">

        <h2>Habilidades</h2>

        <div class="skills">

            @foreach($resume->skills as $skill)

                <span>

                    {{ $skill['name'] }}

                    @if(isset($skill['years']))

                        ({{ $skill['years'] }} years)

                    @endif

                </span>

            @endforeach

        </div>

    </div>

    {{-- LANGUAGES --}}

    <div class="section">

        <h2>Idiomas</h2>

        <ul>

            @foreach($resume->languages as $language)

                <li>

                    {{ $language['language'] }}

                    -

                    {{ ucfirst($language['level']) }}

                </li>

            @endforeach

        </ul>

    </div>

    {{-- CERTIFICATIONS --}}

    @if(!empty($resume->others['certifications']))

        <div class="section">

            <h2>Certificações</h2>

            <ul>

                @foreach($resume->others['certifications'] as $certification)

                    <li>

                        {{ $certification }}

                    </li>

                @endforeach

            </ul>

        </div>

    @endif
</body>

</html>
