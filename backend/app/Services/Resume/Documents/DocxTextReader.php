<?php

namespace App\Services\Resume\Documents;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;
use ZipArchive;

final class DocxTextReader
{
    private const WORD_NAMESPACES = [
        'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
        'http://purl.oclc.org/ooxml/wordprocessingml/main',
    ];

    public function read(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Could not read the DOCX document.', 422);
        }
        try {
            if ($zip->locateName('word/document.xml') === false || $zip->numFiles > 2048) {
                throw new RuntimeException('Invalid DOCX document.', 422);
            }
            $parts = ['word/document.xml'];
            $xmlBytes = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (preg_match('~^word/(header\d+|footer\d+|footnotes|endnotes)\.xml$~', $stat['name'])) {
                    $parts[] = $stat['name'];
                }
            }
            $text = '';
            foreach ($parts as $part) {
                $stat = $zip->statName($part);
                $xmlBytes += $stat['size'];
                if ($xmlBytes > config('resume.documents.max_xml_bytes')) {
                    throw new RuntimeException('The DOCX document exceeds the XML limit.', 422);
                }
                $xml = $zip->getFromName($part);
                if (! is_string($xml) || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
                    throw new RuntimeException('Invalid DOCX XML.', 422);
                }
                $document = new DOMDocument;
                $previous = libxml_use_internal_errors(true);
                try {
                    // No entity substitution, DTD loading, external network or archive extraction.
                    if (! $document->loadXML($xml, LIBXML_NONET) || $document->doctype !== null) {
                        throw new RuntimeException('Invalid DOCX XML.', 422);
                    }
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
                if (! in_array($document->documentElement->namespaceURI, self::WORD_NAMESPACES, true)) {
                    throw new RuntimeException('Invalid DOCX namespace.', 422);
                }
                $links = [];
                $relationships = 'word/_rels/'.basename($part).'.rels';
                if ($zip->locateName($relationships) !== false) {
                    $xmlBytes += $zip->statName($relationships)['size'];
                    if ($xmlBytes > config('resume.documents.max_xml_bytes')) {
                        throw new RuntimeException('The DOCX document exceeds the XML limit.', 422);
                    }
                    $relationshipsXml = $zip->getFromName($relationships);
                    $rels = new DOMDocument;
                    $previous = libxml_use_internal_errors(true);
                    try {
                        if (! is_string($relationshipsXml) || stripos($relationshipsXml, '<!DOCTYPE') !== false
                            || ! $rels->loadXML($relationshipsXml, LIBXML_NONET) || $rels->doctype !== null) {
                            throw new RuntimeException('Invalid DOCX relationships.', 422);
                        }
                    } finally {
                        libxml_clear_errors();
                        libxml_use_internal_errors($previous);
                    }
                    foreach ($rels->getElementsByTagName('Relationship') as $relationship) {
                        $target = $relationship->getAttribute('Target');
                        if (str_ends_with($relationship->getAttribute('Type'), '/hyperlink')
                            && preg_match('~^(https?://|mailto:)~i', $target)) {
                            $links[$relationship->getAttribute('Id')] = $target;
                        }
                    }
                }
                $text .= $this->text($document->documentElement, $links)."\n";
                if (strlen($text) > config('resume.documents.max_text_bytes')) {
                    throw new RuntimeException('The extracted document exceeds the text limit.', 422);
                }
            }

            return $text;
        } finally {
            $zip->close();
        }
    }

    private function text(DOMNode $node, array $links): string
    {
        $word = in_array($node->namespaceURI, self::WORD_NAMESPACES, true);
        if ($word && $node->localName === 't') {
            return $node->textContent;
        }
        if ($word && in_array($node->localName, ['tab', 'br', 'cr'], true)) {
            return $node->localName === 'tab' ? "\t" : "\n";
        }
        // Deleted revisions and field instructions are not visible document text.
        if ($word && in_array($node->localName, ['del', 'instrText'], true)) {
            return '';
        }
        $result = '';
        foreach ($node->childNodes as $child) {
            $result .= $this->text($child, $links);
        }
        if ($word && $node->localName === 'hyperlink' && $node instanceof DOMElement) {
            $id = $node->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')
                ?: $node->getAttributeNS('http://purl.oclc.org/ooxml/officeDocument/relationships', 'id');
            if (isset($links[$id]) && ! str_contains($result, $links[$id])) {
                $result .= ' ('.$links[$id].')';
            }
        }

        return $result.($word && in_array($node->localName, ['p', 'tr'], true) ? "\n" : '');
    }
}
