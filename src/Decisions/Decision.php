<?php

namespace MathiasOnea\Rulebook\Decisions;

use DateTimeImmutable;
use MathiasOnea\Rulebook\Contracts\Rule;
use MathiasOnea\Rulebook\Evaluations\Evaluation;
use MathiasOnea\Rulebook\Evaluations\RuleEvaluation;
use MathiasOnea\Rulebook\Results\RuleResult;

/**
 * @template TSubject of object
 * @template TContext of object|null
 * @template TOutcome
 */
final readonly class Decision
{
    /**
     * @internal Decisions are created by Evaluation::resolve().
     *
     * @param  Evaluation<TSubject, TContext, TOutcome>  $evaluation
     * @param  RuleEvaluation<TSubject, TContext, TOutcome>  $winner
     */
    public function __construct(
        private Evaluation $evaluation,
        private RuleEvaluation $winner,
    ) {}

    /**
     * @return TOutcome
     */
    public function outcome(): mixed
    {
        return $this->winner->result()->outcome();
    }

    /**
     * @return Rule<TSubject, TContext, TOutcome>
     */
    public function winningRule(): Rule
    {
        return $this->winner->rule();
    }

    /**
     * @return RuleResult<TOutcome>
     */
    public function winningResult(): RuleResult
    {
        return $this->winner->result();
    }

    /**
     * @return Evaluation<TSubject, TContext, TOutcome>
     */
    public function evaluation(): Evaluation
    {
        return $this->evaluation;
    }

    /**
     * @return list<RuleEvaluation<TSubject, TContext, TOutcome>>
     */
    public function evaluations(): array
    {
        return $this->evaluation->evaluations();
    }

    /**
     * @return list<Rule<TSubject, TContext, TOutcome>>
     */
    public function applicableRules(): array
    {
        return $this->evaluation->applicableRules();
    }

    /**
     * @return list<Rule<TSubject, TContext, TOutcome>>
     */
    public function inapplicableRules(): array
    {
        return $this->evaluation->inapplicableRules();
    }

    /**
     * @return list<Rule<TSubject, TContext, TOutcome>>
     */
    public function shadowedRules(): array
    {
        return $this->evaluation->shadowedRules();
    }

    public function evaluatedAt(): DateTimeImmutable
    {
        return $this->evaluation->evaluatedAt();
    }
}
