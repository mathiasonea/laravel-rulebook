<?php

namespace MathiasOnea\Rulebook\Exceptions;

use LogicException;

final class DuplicateRuleKey extends LogicException
{
    public function __construct(
        public readonly string $key,
        public readonly string $firstRule,
        public readonly string $duplicateRule,
    ) {
        parent::__construct(sprintf(
            'Rule key [%s] is registered by both [%s] and [%s].',
            $key,
            $firstRule,
            $duplicateRule,
        ));
    }
}
