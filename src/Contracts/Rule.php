<?php

namespace MathiasOnea\Rulebook\Contracts;

use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Periods\ValidityPeriod;
use MathiasOnea\Rulebook\Results\RuleResult;

/**
 * @template TSubject of object
 * @template TContext of object|null
 *
 * @template-covariant TOutcome
 */
interface Rule
{
    public function key(): string;

    public function validity(): ValidityPeriod;

    public function priority(): int;

    /**
     * @param  RuleInput<TSubject, TContext>  $input
     * @return RuleResult<TOutcome>
     */
    public function evaluate(RuleInput $input): RuleResult;
}
