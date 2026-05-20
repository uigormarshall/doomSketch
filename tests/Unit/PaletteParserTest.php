<?php

use App\Support\PaletteParser;

it('parses a lospec hex file (one hex per line, no #)', function () {
    $content = "0f380f\n306230\n8bac0f\n9bbc0f\n";

    expect(PaletteParser::parseHex($content))->toBe([
        '#0f380f',
        '#306230',
        '#8bac0f',
        '#9bbc0f',
    ]);
});

it('accepts leading # and mixed case, normalizes to lowercase', function () {
    $content = "#FF0000\n00Ff00\n#0000ff";

    expect(PaletteParser::parseHex($content))->toBe(['#ff0000', '#00ff00', '#0000ff']);
});

it('skips empty lines, comments and invalid tokens', function () {
    $content = <<<HEX
    ; Paint.NET TXT palette
    // a comment

    0f380f
    not-a-color
    306230
    HEX;

    expect(PaletteParser::parseHex($content))->toBe(['#0f380f', '#306230']);
});

it('strips alpha from AARRGGBB tokens', function () {
    $content = "FF0f380f\nFF306230";

    expect(PaletteParser::parseHex($content))->toBe(['#0f380f', '#306230']);
});

it('deduplicates repeated colors preserving order', function () {
    $content = "0f380f\n306230\n0f380f";

    expect(PaletteParser::parseHex($content))->toBe(['#0f380f', '#306230']);
});

it('caps the result at 16 colors', function () {
    $lines = collect(range(0, 19))
        ->map(fn ($i) => str_pad(dechex($i * 16), 6, '0', STR_PAD_LEFT))
        ->implode("\n");

    expect(PaletteParser::parseHex($lines))->toHaveCount(16);
});

it('throws when no valid color is present', function () {
    expect(fn () => PaletteParser::parseHex("; only comments\nnot a color\n"))
        ->toThrow(InvalidArgumentException::class);
});
