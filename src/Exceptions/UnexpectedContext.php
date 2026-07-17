<?php

namespace MathiasOnea\Rulebook\Exceptions;

use InvalidArgumentException;

final class UnexpectedContext extends InvalidArgumentException
{
    /**
     * @param  class-string  $expected
     */
    public function __construct(
        public readonly string $expected,
        public readonly ?object $actual,
    ) {
        parent::__construct(sprintf(
            'Expected rule context of type [%s], received [%s].',
            $expected,
            $actual === null ? 'null' : $actual::class,
        ));
    }
}
