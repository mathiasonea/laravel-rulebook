<?php

namespace MathiasOnea\Rulebook\Exceptions;

use InvalidArgumentException;

final class UnexpectedSubject extends InvalidArgumentException
{
    /**
     * @param  class-string  $expected
     */
    public function __construct(
        public readonly string $expected,
        public readonly object $actual,
    ) {
        parent::__construct(sprintf(
            'Expected rule subject of type [%s], received [%s].',
            $expected,
            $actual::class,
        ));
    }
}
