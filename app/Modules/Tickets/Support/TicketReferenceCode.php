<?php

namespace App\Modules\Tickets\Support;

use RuntimeException;

class TicketReferenceCode
{
    private const FALLBACK_PREFIX = 'SET';

    private const IGNORED_PREFIX_PARTS = [
        'de',
        'da',
        'do',
        'das',
        'dos',
        'e',
    ];

    private const RANDOM_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @param  callable(string):bool  $lookupExists
     * @return array{reference_code:string,reference_lookup:string}
     */
    public static function generateUniqueForSectorSlug(?string $sectorSlug, callable $lookupExists, int $maxAttempts = 50): array
    {
        $prefix = self::prefixFromSectorSlug($sectorSlug);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $referenceCode = self::compose($prefix, self::randomSuffix());
            $referenceLookup = self::normalizeLookup($referenceCode);

            if (! $lookupExists($referenceLookup)) {
                return [
                    'reference_code' => $referenceCode,
                    'reference_lookup' => $referenceLookup,
                ];
            }
        }

        throw new RuntimeException('Nao foi possivel gerar um codigo publico unico para o chamado.');
    }

    public static function compose(string $prefix, string $suffix): string
    {
        return strtoupper($prefix).'-'.strtoupper($suffix);
    }

    public static function normalizeLookup(?string $referenceCode): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]+/', '', strtoupper((string) $referenceCode));

        return is_string($normalized) ? $normalized : '';
    }

    public static function prefixFromSectorSlug(?string $sectorSlug): string
    {
        $parts = collect(preg_split('/[^a-z0-9]+/i', strtolower((string) $sectorSlug)) ?: [])
            ->map(fn (mixed $part) => is_string($part) ? trim($part) : '')
            ->filter()
            ->reject(fn (string $part) => in_array($part, self::IGNORED_PREFIX_PARTS, true))
            ->values();

        if ($parts->count() >= 2) {
            $prefix = $parts
                ->take(3)
                ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
                ->implode('');

            return $prefix !== '' ? $prefix : self::FALLBACK_PREFIX;
        }

        if ($parts->count() === 1) {
            $prefix = strtoupper(substr((string) $parts->first(), 0, 3));

            return $prefix !== '' ? $prefix : self::FALLBACK_PREFIX;
        }

        return self::FALLBACK_PREFIX;
    }

    public static function randomSuffix(int $length = 5): string
    {
        $alphabet = self::RANDOM_ALPHABET;
        $maxIndex = strlen($alphabet) - 1;
        $suffix = '';

        for ($index = 0; $index < $length; $index++) {
            $suffix .= $alphabet[random_int(0, $maxIndex)];
        }

        return $suffix;
    }
}
