<?php

use App\Services\Resume\JsonObject;

it('preserves empty and numeric-keyed objects separately from arrays', function () {
    $value = JsonObject::decode('{"empty":{},"numeric":{"0":"PHP"},"list":[],"nested":{"objects":[{}]}}');
    expect($value['empty'])->toBeInstanceOf(stdClass::class)
        ->and($value['numeric'])->toBeInstanceOf(stdClass::class)
        ->and($value['list'])->toBe([])
        ->and($value['nested']['objects'][0])->toBeInstanceOf(stdClass::class);
});

it('rejects malformed JSON and nonobject roots', function ($json) {
    expect(fn () => JsonObject::decode($json))->toThrow(RuntimeException::class);
})->with(['', '{', '[]', 'null', 'true', '123', '"text"']);
