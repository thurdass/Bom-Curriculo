<!DOCTYPE html>
<html lang="{{ $document['lang'] }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $document['name'] ?: 'Currículo' }}</title>
    <style>
        @page { margin: 14mm 16mm; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.2; color: #202020; }
        h1 { margin: 0 0 3pt; font-size: 19pt; line-height: 1.15; }
        h2 { margin: 9pt 0 3pt; padding-bottom: 3pt; border-bottom: 0.5pt solid #aaa; font-size: 11pt; page-break-after: avoid; }
        h3 { margin: 0 0 2pt; font-size: 10pt; }
        p { margin: 0 0 2pt; orphans: 3; widows: 3; }
        .header { page-break-inside: avoid; }
        .headline { font-size: 10.5pt; margin-bottom: 4pt; }
        .contacts, .links, .meta, .details { font-size: 9pt; }
        .meta { color: #444; }
        .entry { margin-bottom: 5pt; }
        .entry.compact { page-break-inside: avoid; }
        .entry-heading { page-break-inside: avoid; page-break-after: avoid; }
        .description { white-space: pre-line; }
        .text, .links { overflow-wrap: break-word; word-wrap: break-word; }
    </style>
</head>
<body>
    <div class="header text">
        @if($document['name'] !== '')<h1>{{ $document['name'] }}</h1>@endif
        @if($document['headline'] !== '')<p class="headline">{{ $document['headline'] }}</p>@endif
        @if($document['contacts'] !== [])<p class="contacts">{{ implode(' · ', $document['contacts']) }}</p>@endif
        @foreach($document['links'] as $link)
            <p class="links">{{ $link['label'] !== '' ? $link['label'].': ' : '' }}{{ $link['url'] }}</p>
        @endforeach
    </div>

    @if($document['summary'] !== '')
        <h2>{{ $document['labels']['summary'] }}</h2>
        <p class="description text">{{ $document['summary'] }}</p>
    @endif

    @foreach($document['sections'] as $section)
        <h2>{{ $section['title'] }}</h2>
        @foreach($section['items'] as $item)
            {{-- Keep short entries together; longer descriptions must remain free to span pages. --}}
            <div class="entry text {{ mb_strlen(implode(' ', $item)) < 450 && substr_count($item['description'], "\n") < 5 ? 'compact' : '' }}">
                @if($item['title'] !== '' || $item['subtitle'] !== '' || $item['meta'] !== '')
                    <div class="entry-heading">
                        @if($item['title'] !== '')<h3>{{ $item['title'] }}</h3>@endif
                        @if($item['subtitle'] !== '')<p>{{ $item['subtitle'] }}</p>@endif
                        @if($item['meta'] !== '')<p class="meta">{{ $item['meta'] }}</p>@endif
                    </div>
                @endif
                @if($item['description'] !== '')<p class="description">{{ $item['description'] }}</p>@endif
                @if($item['url'] !== '' || $item['technologies'] !== '')
                    <p class="details">
                        {{ $item['url'] }}
                        @if($item['url'] !== '' && $item['technologies'] !== '') · @endif
                        @if($item['technologies'] !== ''){{ $document['labels']['technologies'] }}: {{ $item['technologies'] }}@endif
                    </p>
                @endif
            </div>
        @endforeach
    @endforeach

    @foreach(['skills', 'languages'] as $list)
        @if($document[$list] !== [])
            <h2>{{ $document['labels'][$list] }}</h2>
            <p class="text">{{ implode(' · ', $document[$list]) }}</p>
        @endif
    @endforeach
</body>
</html>
