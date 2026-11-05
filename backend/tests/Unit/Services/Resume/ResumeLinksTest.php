<?php

use App\Services\Resume\ResumeLinks;

it('preserves reported links over case variant guesses and removes duplicate destinations', function () {
    $links = ResumeLinks::merge([
        'github' => 'https://github.com/wrong', 'Github' => 'https://github.com/also-wrong',
        'Website' => 'https://example.test', 'Portfolio' => 'https://example.test',
        'Other' => 'https://example.test/other',
    ], ['GitHub' => 'https://github.com/right']);
    expect($links)->toBe([
        'Website' => 'https://example.test', 'Other' => 'https://example.test/other', 'GitHub' => 'https://github.com/right',
    ]);
});
