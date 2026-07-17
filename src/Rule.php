<?php

namespace MathiasOnea\Rulebook;

use MathiasOnea\Rulebook\Contracts\Rule as RuleContract;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Periods\ValidityPeriod;
use MathiasOnea\Rulebook\Results\RuleResult;

/**
 * @template TSubject of object
 * @template TContext of object|null
 *
 * @template-covariant TOutcome
 *
 * @implements RuleContract<TSubject, TContext, TOutcome>
 */
abstract class Rule implements RuleContract
{
    public function key(): string
    {
        return static::class;
    }

    public function validity(): ValidityPeriod
    {
        return ValidityPeriod::always();
    }

    public function priority(): int
    {
        return 0;
    }

    /**
     * @param  RuleInput<TSubject, TContext>  $input
     * @return RuleResult<TOutcome>
     */
    abstract public function evaluate(RuleInput $input): RuleResult;
}
