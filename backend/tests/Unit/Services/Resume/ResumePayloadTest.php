<?php

use App\Services\Resume\ResumePayload;

it('rejects malformed generated fields instead of persisting a partially usable resume', function (string $path, mixed $value) {
    $payload = json_decode(file_get_contents(base_path('tests/Fixtures/generated_resume_payload.json')), true);
    $payload['others'] = (object) [];
    data_set($payload, $path, $value);
    expect(fn () => ResumePayload::validate($payload))->toThrow(InvalidArgumentException::class);
})->with([
    ['score', 101], ['score', '88'], ['skills.0.years', 1.5],
    ['languages.0.level', 'expert'], ['experiences.0.company', []],
    ['header.links', []], ['others', []], ['unexpected', true],
]);
