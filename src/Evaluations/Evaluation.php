<?php

namespace MathiasOnea\Rulebook\Evaluations;

use DateTimeImmutable;
use MathiasOnea\Rulebook\Contracts\Rule;
use MathiasOnea\Rulebook\Decisions\Decision;
use MathiasOnea\Rulebook\Exceptions\AmbiguousRuleMatch;
use MathiasOnea\Rulebook\Exceptions\NoMatchingRule;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Snapshots\EvaluationSnapshot;
use MathiasOnea\Rulebook\Snapshots\RuleEvaluationSnapshot;

/**
 * @template TSubject of object
 * @template TContext of object|null
 * @template TOutcome
 */
final readonly class Evaluation
{
    /**
     * @param  RuleInput<TSubject, TContext>  $input
     * @param  list<RuleEvaluation<TSubject, TContext, TOutcome>>  $evaluations
     */
    public function __construct(
        private RuleInput $input,
        private array $evaluations,
    ) {}

    /**
     * @return RuleInput<TSubject, TContext>
     */
    public function input(): RuleInput
    {
        return $this->input;
    }

    public function evaluatedAt(): DateTimeImmutable
    {
        return $this->input->at;
    }

    /**
     * @return list<RuleEvaluation<TSubject, TContext, TOutcome>>
     */
    public function evaluations(): array
    {
        return $this->evaluations;
    }

    /**
     * @return list<RuleEvaluation<TSubject, TContext, TOutcome>>
     */
    public function applicableEvaluations(): array
    {
        return array_values(array_filter(
            $this->evaluations,
            static fn (RuleEvaluation $evaluation): bool => $evaluation->isApplicable(),
        ));
    }

    /**
     * @return list<RuleEvaluation<TSubject, TContext, TOutcome>>
     */
    public function inapplicableEvaluations(): array
    {
        return array_values(array_filter(
            $this->evaluations,
            static fn (RuleEvaluation $evaluation): bool => $evaluation->isInapplicable(),
        ));
    }

    /**
     * @return list<Rule<TSubject, TContext, TOutcome>>
     */
    public function applicableRules(): array
    {
        return array_map(
            static fn (RuleEvaluation $evaluation): Rule => $evaluation->rule(),
            $this->applicableEvaluations(),
        );
    }

    /**
     * @return list<Rule<TSubject, TContext, TOutcome>>
     */
    public function inapplicableRules(): array
    {
        return array_map(
            static fn (RuleEvaluation $evaluation): Rule => $evaluation->rule(),
            $this->inapplicableEvaluations(),
        );
    }

    /**
     * @return list<RuleEvaluation<TSubject, TContext, TOutcome>>
     */
    public function topApplicableEvaluations(): array
    {
        $applicable = $this->applicableEvaluations();

        if ($applicable === []) {
            return [];
        }

        $highestPriority = $applicable[0]->priority();

        foreach ($applicable as $evaluation) {
            $highestPriority = max($highestPriority, $evaluation->priority());
        }

        return array_values(array_filter(
            $applicable,
            static fn (RuleEvaluation $evaluation): bool => $evaluation->priority() === $highestPriority,
        ));
    }

    /**
     * @return list<Rule<TSubject, TContext, TOutcome>>
     */
    public function shadowedRules(): array
    {
        return array_map(
            static fn (RuleEvaluation $evaluation): Rule => $evaluation->rule(),
            $this->shadowedEvaluations(),
        );
    }

    /**
     * @return list<RuleEvaluation<TSubject, TContext, TOutcome>>
     */
    public function shadowedEvaluations(): array
    {
        $top = $this->topApplicableEvaluations();

        if ($top === []) {
            return [];
        }

        $highestPriority = $top[0]->priority();

        return array_values(array_filter(
            $this->applicableEvaluations(),
            static fn (RuleEvaluation $evaluation): bool => $evaluation->priority() < $highestPriority,
        ));
    }

    /**
     * @return list<RuleEvaluation<TSubject, TContext, TOutcome>>
     */
    public function conflictingEvaluations(): array
    {
        $top = $this->topApplicableEvaluations();

        return count($top) > 1 ? $top : [];
    }

    /**
     * @return RuleEvaluation<TSubject, TContext, TOutcome>|null
     */
    public function evaluationFor(string $key): ?RuleEvaluation
    {
        foreach ($this->evaluations as $evaluation) {
            if ($evaluation->key() === $key) {
                return $evaluation;
            }
        }

        return null;
    }

    public function hasWinner(): bool
    {
        return count($this->topApplicableEvaluations()) === 1;
    }

    public function hasConflict(): bool
    {
        return count($this->topApplicableEvaluations()) > 1;
    }

    /**
     * @return RuleEvaluation<TSubject, TContext, TOutcome>|null
     */
    public function winningEvaluation(): ?RuleEvaluation
    {
        $top = $this->topApplicableEvaluations();

        return count($top) === 1 ? $top[0] : null;
    }

    /**
     * @return Decision<TSubject, TContext, TOutcome>
     *
     * @throws NoMatchingRule<TSubject, TContext, TOutcome>
     * @throws AmbiguousRuleMatch<TSubject, TContext, TOutcome>
     */
    public function resolve(): Decision
    {
        $top = $this->topApplicableEvaluations();

        if ($top === []) {
            throw new NoMatchingRule($this);
        }

        if (count($top) > 1) {
            throw new AmbiguousRuleMatch($this);
        }

        return new Decision($this, $top[0]);
    }

    public function snapshot(): EvaluationSnapshot
    {
        return new EvaluationSnapshot(
            evaluatedAt: $this->evaluatedAt(),
            evaluations: array_map(
                static fn (RuleEvaluation $evaluation): RuleEvaluationSnapshot => $evaluation->snapshot(),
                $this->evaluations,
            ),
        );
    }
}
