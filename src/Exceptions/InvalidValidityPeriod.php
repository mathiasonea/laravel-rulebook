<?php

namespace MathiasOnea\Rulebook\Exceptions;

use DateTimeImmutable;
use InvalidArgumentException;

final class InvalidValidityPeriod extends InvalidArgumentException
{
    public function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $until,
    ) {
        parent::__construct(sprintf(
            'A validity period must end after it starts; received [%s, %s).',
            $from->format('Y-m-d\TH:i:s.uP'),
            $until->format('Y-m-d\TH:i:s.uP'),
        ));
    }
}
