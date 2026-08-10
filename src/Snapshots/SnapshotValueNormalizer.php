<?php

namespace MathiasOnea\Rulebook\Snapshots;

use BackedEnum;
use JsonSerializable;
use MathiasOnea\Rulebook\Exceptions\UnportableSnapshotValue;

/**
 * @internal Snapshot values are normalized by DecisionSnapshot.
 */
final class SnapshotValueNormalizer
{
    private const MAX_DEPTH = 64;

    /**
     * @return array<array-key, mixed>|bool|float|int|string|null
     */
    public static function normalize(mixed $value): array|bool|float|int|string|null
    {
        $objects = [];

        return self::normalizeAt($value, 'outcome', 0, $objects, true);
    }

    /**
     * @return array<array-key, mixed>|bool|float|int|string|null
     */
    public static function copy(mixed $value): array|bool|float|int|string|null
    {
        $objects = [];

        return self::normalizeAt($value, 'outcome', 0, $objects, false);
    }

    /**
     * @param  array<int, true>  $objects
     * @return array<array-key, mixed>|bool|float|int|string|null
     */
    private static function normalizeAt(
        mixed $value,
        string $path,
        int $depth,
        array &$objects,
        bool $normalizeObjects,
    ): array|bool|float|int|string|null {
        if ($depth > self::MAX_DEPTH) {
            throw new UnportableSnapshotValue($path, 'maximum depth exceeded');
        }

        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new UnportableSnapshotValue($path, get_debug_type($value));
            }

            return $value;
        }

        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new UnportableSnapshotValue($path, 'invalid UTF-8 string');
            }

            return $value;
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                if (is_string($key) && preg_match('//u', $key) !== 1) {
                    throw new UnportableSnapshotValue($path, 'invalid UTF-8 array key');
                }

                $normalized[$key] = self::normalizeAt(
                    $item,
                    sprintf('%s.%s', $path, $key),
                    $depth + 1,
                    $objects,
                    $normalizeObjects,
                );
            }

            return $normalized;
        }

        if ($normalizeObjects && $value instanceof BackedEnum) {
            return self::normalizeAt($value->value, $path, $depth + 1, $objects, true);
        }

        if ($normalizeObjects && $value instanceof JsonSerializable) {
            $objectId = spl_object_id($value);

            if (isset($objects[$objectId])) {
                throw new UnportableSnapshotValue($path, $value::class.' (cyclic)');
            }

            $objects[$objectId] = true;

            try {
                return self::normalizeAt($value->jsonSerialize(), $path, $depth + 1, $objects, true);
            } finally {
                unset($objects[$objectId]);
            }
        }

        throw new UnportableSnapshotValue($path, get_debug_type($value));
    }
}
