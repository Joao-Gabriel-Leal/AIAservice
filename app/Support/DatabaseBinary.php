<?php

namespace App\Support;

class DatabaseBinary
{
    public static function encode(?string $content, string $driver): ?string
    {
        if ($content === null) {
            return null;
        }

        return $driver === 'pgsql'
            ? '\\x'.bin2hex($content)
            : $content;
    }

    public static function decode(mixed $value): ?string
    {
        if (is_resource($value)) {
            $value = stream_get_contents($value);

            if ($value === false) {
                return null;
            }
        }

        if (! is_string($value)) {
            return null;
        }

        if (self::isHexEncodedBytea($value)) {
            $decoded = hex2bin(substr($value, 2));

            return $decoded === false ? null : $decoded;
        }

        return $value;
    }

    private static function isHexEncodedBytea(string $value): bool
    {
        if (! str_starts_with($value, '\\x')) {
            return false;
        }

        $hex = substr($value, 2);

        return $hex !== ''
            && strlen($hex) % 2 === 0
            && ctype_xdigit($hex);
    }
}
