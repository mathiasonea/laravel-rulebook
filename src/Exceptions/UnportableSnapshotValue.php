<?php

namespace MathiasOnea\Rulebook\Exceptions;

use InvalidArgumentException;

final class UnportableSnapshotValue extends InvalidArgumentException
{
    public function __construct(
        public readonly string $path,
        public readonly string $type,
    ) {
        parent::__construct(sprintf(
            'Snapshot value at [%s] must be JSON-compatible; received [%s].',
            $path,
            $type,
        ));
    }
}
