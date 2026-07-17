<?php

namespace MathiasOnea\Rulebook\Evaluations;

use MathiasOnea\Rulebook\Contracts\Rule;
use MathiasOnea\Rulebook\Results\RuleResult;

/**
 * @template TSubject of object
 * @template TContext of object|null
 *
 * @template-covariant TOutcome
 */
final readonly class RuleEvaluation
{
    /**
     * @param  Rule<TSubject, TContext, TOutcome>  $rule
     * @param  RuleResult<TOutcome>  $result
     */
    public function __construct(
        private Rule $rule,
        private RuleResult $result,
        private bool $evaluated,
    ) {}

    /**
     * @return Rule<TSubject, TContext, TOutcome>
     */
    public function rule(): Rule
    {
        return $this->rule;
    }

    /**
     * @return RuleResult<TOutcome>
     */
    public function result(): RuleResult
    {
        return $this->result;
    }

    public function priority(): int
    {
        return $this->rule->priority();
    }

    public function isApplicable(): bool
    {
        return $this->result->isApplicable();
    }

    public function isInapplicable(): bool
    {
        return $this->result->isInapplicable();
    }

    public function wasEvaluated(): bool
    {
        return $this->evaluated;
    }
}
