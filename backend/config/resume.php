<?php

return [
    'output_language' => env('RESUME_OUTPUT_LANGUAGE', 'pt-BR'),
    'documents' => [
        'pdftotext_binary' => env('PDFTOTEXT_BINARY', '/usr/bin/pdftotext'),
        'timeout_seconds' => (int) env('RESUME_DOCUMENT_TIMEOUT_SECONDS', 10),
        'max_file_bytes' => 10 * 1024 * 1024,
        'max_xml_bytes' => 16 * 1024 * 1024,
        'max_text_bytes' => 256 * 1024,
    ],
    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'timeout_seconds' => (int) env('GITHUB_TIMEOUT_SECONDS', 10),
    ],
    'ollama' => [
        'url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen3:4b'),
        'connect_timeout_seconds' => (int) env('OLLAMA_CONNECT_TIMEOUT_SECONDS', 5),
        'timeout_seconds' => (int) env('OLLAMA_TIMEOUT_SECONDS', 240),
        'temperature' => (float) env('OLLAMA_TEMPERATURE', 0.2),
        'num_predict' => (int) env('OLLAMA_NUM_PREDICT', 2048),
        'num_ctx' => (int) env('OLLAMA_NUM_CTX', 16384),
        'keep_alive' => env('OLLAMA_KEEP_ALIVE', '5m'),
        // Reject oversized sources explicitly instead of silently truncating the CV.
        'max_source_characters' => (int) env('RESUME_MAX_SOURCE_CHARACTERS', 30000),
    ],
];
