<?php

namespace MathiasOnea\Rulebook\Decisions;

use DateTimeImmutable;
use MathiasOnea\Rulebook\Contracts\Rule;
use MathiasOnea\Rulebook\Evaluations\Evaluation;
use MathiasOnea\Rulebook\Evaluations\RuleEvaluation;
use MathiasOnea\Rulebook\Results\RuleResult;
use MathiasOnea\Rulebook\Snapshots\DecisionSnapshot;

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

    public function winningRuleKey(): string
    {
        return $this->winner->key();
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
     * @return RuleEvaluation<TSubject, TContext, TOutcome>|null
     */
    public function evaluationFor(string $key): ?RuleEvaluation
    {
        return $this->evaluation->evaluationFor($key);
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

    /**
     * @return list<RuleEvaluation<TSubject, TContext, TOutcome>>
     */
    public function shadowedEvaluations(): array
    {
        return $this->evaluation->shadowedEvaluations();
    }

    public function evaluatedAt(): DateTimeImmutable
    {
        return $this->evaluation->evaluatedAt();
    }

    /**
     * @param  (callable(TOutcome): (array<array-key, mixed>|bool|float|int|string|null))|null  $normalizeOutcome
     */
    public function snapshot(?callable $normalizeOutcome = null): DecisionSnapshot
    {
        $outcome = $this->outcome();

        if ($normalizeOutcome === null) {
            return DecisionSnapshot::fromOutcome($this->evaluation->snapshot(), $outcome);
        }

        return DecisionSnapshot::fromNormalizedOutcome(
            $this->evaluation->snapshot(),
            $normalizeOutcome($outcome),
        );
    }
}
