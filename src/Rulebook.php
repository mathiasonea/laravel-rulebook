<?php

namespace MathiasOnea\Rulebook;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use MathiasOnea\Rulebook\Contracts\Rule as RuleContract;
use MathiasOnea\Rulebook\Decisions\Decision;
use MathiasOnea\Rulebook\Evaluations\Evaluation;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Resolution\RuleResolver;

/**
 * @template TSubject of object
 * @template TContext of object|null
 * @template TOutcome
 */
abstract class Rulebook
{
    public function __construct(private readonly RuleResolver $resolver) {}

    /**
     * @return list<class-string<RuleContract<TSubject, TContext, TOutcome>>>
     */
    abstract protected function rules(): array;

    /**
     * @param  TSubject  $subject
     * @param  TContext  $context
     * @return Evaluation<TSubject, TContext, TOutcome>
     */
    final public function evaluateNow(object $subject, ?object $context = null): Evaluation
    {
        return $this->evaluateAt(
            subject: $subject,
            at: DateTimeImmutable::createFromInterface(CarbonImmutable::now()),
            context: $context,
        );
    }

    /**
     * @param  TSubject  $subject
     * @param  TContext  $context
     * @return Evaluation<TSubject, TContext, TOutcome>
     */
    final public function evaluateAt(
        object $subject,
        DateTimeInterface $at,
        ?object $context = null,
    ): Evaluation {
        /** @var RuleInput<TSubject, TContext> $input */
        $input = new RuleInput(
            subject: $subject,
            context: $context,
            at: DateTimeImmutable::createFromInterface($at),
        );

        return $this->resolver->evaluate($this->rules(), $input);
    }

    /**
     * @param  TSubject  $subject
     * @param  TContext  $context
     * @return Decision<TSubject, TContext, TOutcome>
     */
    final public function resolveNow(object $subject, ?object $context = null): Decision
    {
        return $this->evaluateNow($subject, $context)->resolve();
    }

    /**
     * @param  TSubject  $subject
     * @param  TContext  $context
     * @return Decision<TSubject, TContext, TOutcome>
     */
    final public function resolveAt(
        object $subject,
        DateTimeInterface $at,
        ?object $context = null,
    ): Decision {
        return $this->evaluateAt($subject, $at, $context)->resolve();
    }
}
