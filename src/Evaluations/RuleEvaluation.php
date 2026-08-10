<?php

namespace MathiasOnea\Rulebook\Evaluations;

use InvalidArgumentException;
use MathiasOnea\Rulebook\Contracts\Rule;
use MathiasOnea\Rulebook\Periods\ValidityPeriod;
use MathiasOnea\Rulebook\Results\RuleResult;
use MathiasOnea\Rulebook\Snapshots\RuleEvaluationSnapshot;

/**
 * @template TSubject of object
 * @template TContext of object|null
 *
 * @template-covariant TOutcome
 */
final readonly class RuleEvaluation
{
    private string $key;

    private int $priority;

    private ValidityPeriod $validity;

    private RuleEvaluationStatus $status;

    /**
     * @param  Rule<TSubject, TContext, TOutcome>  $rule
     * @param  RuleResult<TOutcome>  $result
     */
    public function __construct(
        private Rule $rule,
        private RuleResult $result,
        bool $evaluated,
        ?string $key = null,
        ?int $priority = null,
        ?ValidityPeriod $validity = null,
    ) {
        if (! $evaluated && $result->isApplicable()) {
            throw new InvalidArgumentException('An unevaluated rule cannot have an applicable result.');
        }

        $this->key = $key ?? $rule->key();
        $this->priority = $priority ?? $rule->priority();
        $this->validity = $validity ?? $rule->validity();
        $this->status = match (true) {
            ! $evaluated => RuleEvaluationStatus::OutsideValidity,
            $result->isApplicable() => RuleEvaluationStatus::Applicable,
            default => RuleEvaluationStatus::DoesNotApply,
        };
    }

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

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return class-string<Rule<TSubject, TContext, TOutcome>>
     */
    public function ruleClass(): string
    {
        return $this->rule::class;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function validity(): ValidityPeriod
    {
        return $this->validity;
    }

    public function status(): RuleEvaluationStatus
    {
        return $this->status;
    }

    public function isApplicable(): bool
    {
        return $this->status === RuleEvaluationStatus::Applicable;
    }

    public function isInapplicable(): bool
    {
        return $this->status !== RuleEvaluationStatus::Applicable;
    }

    public function wasEvaluated(): bool
    {
        return $this->status !== RuleEvaluationStatus::OutsideValidity;
    }

    public function snapshot(): RuleEvaluationSnapshot
    {
        return new RuleEvaluationSnapshot(
            key: $this->key,
            ruleClass: $this->rule::class,
            priority: $this->priority,
            validFrom: $this->validity->startsAt(),
            validUntil: $this->validity->endsAt(),
            status: $this->status,
            reason: $this->result->reason(),
            reasonCode: $this->result->reasonCode(),
        );
    }
}
