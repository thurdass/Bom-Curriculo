<?php

use App\Services\Resume\Documents\DocumentTextExtractor;
use App\Services\Resume\Documents\ResumeContentValidator;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('extracts real documents using native readers', function (string $extension) {
    $path = 'resume.'.$extension;
    Storage::put($path, file_get_contents(base_path('tests/Fixtures/documents/sample_resume.'.$extension)));
    $text = app(DocumentTextExtractor::class)->extract($path);
    expect($text)->toContain('PROFISSIONAL', 'FastAPI', 'PROJETOS');
})->with(['pdf', 'docx']);

it('rejects malformed documents and unsupported formats', function ($path, $content) {
    Storage::put($path, $content);
    expect(fn () => app(DocumentTextExtractor::class)->extract($path))->toThrow(RuntimeException::class);
})->with([
    ['bad.pdf', '%PDF-broken'], ['bad.docx', 'not a zip archive'], ['bad.txt', 'resume text'],
]);

it('rejects missing files, oversized files and LinkedIn DOCX', function () {
    $reader = app(DocumentTextExtractor::class);
    expect(fn () => $reader->extract('missing.pdf'))->toThrow(RuntimeException::class, 'missing');
    Storage::put('resume.docx', file_get_contents(base_path('tests/Fixtures/documents/sample_resume.docx')));
    expect(fn () => $reader->extract('resume.docx', true))->toThrow(RuntimeException::class, 'LinkedIn');
    config(['resume.documents.max_file_bytes' => 10]);
    expect(fn () => $reader->extract('resume.docx'))->toThrow(RuntimeException::class, 'file size');
});

it('rejects DOCX external entities and excessive uncompressed XML', function () {
    $zip = new ZipArchive;
    $path = Storage::path('unsafe.docx');
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('word/document.xml', '<!DOCTYPE x [<!ENTITY secret SYSTEM "file:///etc/passwd">]><x>&secret;</x>');
    $zip->close();
    expect(fn () => app(DocumentTextExtractor::class)->extract('unsafe.docx'))->toThrow(RuntimeException::class, 'XML');
    config(['resume.documents.max_xml_bytes' => 10]);
    expect(fn () => app(DocumentTextExtractor::class)->extract('unsafe.docx'))->toThrow(RuntimeException::class, 'XML limit');
});

it('reports missing PDF dependency without treating it as invalid user input', function () {
    Storage::put('resume.pdf', file_get_contents(base_path('tests/Fixtures/documents/sample_resume.pdf')));
    config(['resume.documents.pdftotext_binary' => '/missing/pdftotext']);
    try {
        app(DocumentTextExtractor::class)->extract('resume.pdf');
        $this->fail('Expected missing dependency failure');
    } catch (RuntimeException $error) {
        expect($error->getCode())->toBe(503);
    }
});

it('preserves DOCX tables, hyperlinks, accented text and headers', function () {
    $zip = new ZipArchive;
    $zip->open(Storage::path('rich.docx'), ZipArchive::CREATE);
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $zip->addFromString('word/document.xml', '<w:document xmlns:w="'.$namespace.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Experiência em integração</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:hyperlink r:id="link1"><w:r><w:t>Portfólio</w:t></w:r></w:hyperlink></w:p></w:body></w:document>');
    $zip->addFromString('word/_rels/document.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="link1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test" TargetMode="External" /></Relationships>');
    $zip->addFromString('word/header1.xml', '<w:hdr xmlns:w="'.$namespace.'"><w:p><w:r><w:t>Cabeçalho</w:t></w:r></w:p></w:hdr>');
    $zip->close();
    expect(app(DocumentTextExtractor::class)->extract('rich.docx'))->toContain('Experiência em integração', 'Portfólio (https://example.test)', 'Cabeçalho');
});

it('bounds extracted text without silently truncating it', function () {
    Storage::put('resume.pdf', file_get_contents(base_path('tests/Fixtures/documents/sample_resume.pdf')));
    config(['resume.documents.max_text_bytes' => 10]);
    expect(fn () => app(DocumentTextExtractor::class)->extract('resume.pdf'))->toThrow(RuntimeException::class, 'text limit');
});

it('preserves the content validation gates', function ($text, $reason) {
    expect(fn () => app(ResumeContentValidator::class)->validate($text))->toThrow(RuntimeException::class, $reason);
})->with([
    ['', 'empty'], ['    ', 'empty'], ['A short resume', 'too_short'],
    [str_repeat('teste ', 50), 'low_content_diversity'],
]);

it('accepts meaningful accented Portuguese resume text', function () {
    app(ResumeContentValidator::class)->validate('Profissional de tecnologia com experiência em desenvolvimento de APIs, análise de requisitos e manutenção de sistemas. Formação em Ciência da Computação, conhecimentos de PHP, SQL, testes automatizados e colaboração em equipes de produto.');
    expect(true)->toBeTrue();
});
