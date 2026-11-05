<?php

use App\Services\Resume\ResumeDocumentPresenter;

it('removes only redundant displayed facts and preserves distinct qualifications', function () {
    $document = app(ResumeDocumentPresenter::class)->present([
        'qualifications' => [
            ['title' => 'Curso SQL', 'institution' => 'Escola A', 'type' => 'extracurricular_course'],
            ['title' => 'Curso SQL', 'institution' => 'Escola B', 'type' => 'extracurricular_course'],
            ['title' => 'Curso SQL', 'institution' => 'Escola A', 'type' => 'extracurricular_course'],
        ],
        'others' => ['certifications' => [
            'Curso SQL', ['title' => 'Curso SQL', 'issuer' => 'Escola C', 'credential_id' => 'ID-123'],
        ]],
        'projects' => [['title' => 'API', 'description' => 'Fato A'], ['title' => 'API', 'description' => 'Fato B']],
    ]);
    expect($document['sections'][0]['items'])->toHaveCount(2)
        ->and($document['sections'][1]['items'])->toHaveCount(3)
        ->and($document['sections'][1]['items'][2]['meta'])->toBe('ID-123');
});

it('omits empty sections and supports legacy arrays without inventing missing data', function () {
    $document = app(ResumeDocumentPresenter::class)->present([
        'header' => ['emails' => ['a@example.test', 'a@example.test'], 'contacts' => null],
        'experiences' => [['role' => null, 'company' => 'Organização']],
        'projects' => [['title' => ' ', 'description' => null]],
        'skills' => [['name' => 'PHP', 'years' => 0], ['name' => 'php', 'years' => 0], ['name' => 'SQL', 'years' => null]],
        'languages' => [['language' => 'Inglês', 'level' => 'C1']],
        'others' => ['certifications' => 'legacy unsupported metadata'],
    ]);
    expect($document['contacts'])->toBe(['a@example.test'])
        ->and($document['sections'])->toHaveCount(1)
        ->and($document['sections'][0]['items'][0]['meta'])->toBe('')
        ->and($document['skills'])->toBe(['PHP (0 anos)', 'SQL'])
        ->and($document['languages'])->toBe(['Inglês — C1']);
});

it('uses English presentation labels when English generation is configured', function () {
    config(['resume.output_language' => 'en']);
    $document = app(ResumeDocumentPresenter::class)->present([
        'skills' => [['name' => 'Java', 'years' => 1]],
        'experiences' => [['role' => 'Engineer', 'is_actual' => true]],
    ]);
    expect($document['lang'])->toBe('en')
        ->and($document['skills'])->toBe(['Java (1 year)'])
        ->and($document['sections'][0]['items'][0]['meta'])->toBe('Present');
});
