<?php

namespace MathiasOnea\Rulebook\Exceptions;

use InvalidArgumentException;

final class InvalidRuleKey extends InvalidArgumentException
{
    public function __construct(public readonly string $rule)
    {
        parent::__construct(sprintf('Rule [%s] must expose a non-empty key.', $rule));
    }
}
