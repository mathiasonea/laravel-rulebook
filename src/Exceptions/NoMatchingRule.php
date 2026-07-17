<?php

namespace MathiasOnea\Rulebook\Exceptions;

use MathiasOnea\Rulebook\Evaluations\Evaluation;
use RuntimeException;

/**
 * @template TSubject of object
 * @template TContext of object|null
 * @template TOutcome
 */
final class NoMatchingRule extends RuntimeException
{
    /**
     * @param  Evaluation<TSubject, TContext, TOutcome>  $evaluation
     */
    public function __construct(public readonly Evaluation $evaluation)
    {
        parent::__construct(sprintf(
            'No rule applies at %s.',
            $evaluation->evaluatedAt()->format('Y-m-d\TH:i:s.uP'),
        ));
    }

    /**
     * @return Evaluation<TSubject, TContext, TOutcome>
     */
    public function evaluation(): Evaluation
    {
        return $this->evaluation;
    }
}
