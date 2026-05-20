<?php

namespace App\Support;

use InvalidArgumentException;

class PaletteParser
{
    private const MAX_COLORS = 16;

    /**
     * Parse a HEX/TXT palette file content into a list of normalized hex colors (#rrggbb, lowercase).
     *
     * Accepts Lospec HEX (one hex per line), Paint.NET TXT (with ';' comments and AARRGGBB hex),
     * or any text file with one hex code per line. Empty lines and comments are ignored.
     *
     * @return array<int, string>
     *
     * @throws InvalidArgumentException
     */
    public static function parseHex(string $content): array
    {
        $colors = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '//')) {
                continue;
            }

            $token = ltrim($line, '#');

            if (! preg_match('/^[0-9a-fA-F]{6}$|^[0-9a-fA-F]{8}$/', $token)) {
                continue;
            }

            if (strlen($token) === 8) {
                $token = substr($token, 2);
            }

            $colors[] = '#'.strtolower($token);
        }

        $colors = array_values(array_unique($colors));

        if ($colors === []) {
            throw new InvalidArgumentException('No valid hex colors found in the file.');
        }

        return array_slice($colors, 0, self::MAX_COLORS);
    }
}
