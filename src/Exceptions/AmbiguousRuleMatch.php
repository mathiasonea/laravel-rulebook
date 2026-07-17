<?php

namespace MathiasOnea\Rulebook\Exceptions;

use MathiasOnea\Rulebook\Evaluations\Evaluation;
use RuntimeException;

/**
 * @template TSubject of object
 * @template TContext of object|null
 * @template TOutcome
 */
final class AmbiguousRuleMatch extends RuntimeException
{
    /**
     * @param  Evaluation<TSubject, TContext, TOutcome>  $evaluation
     */
    public function __construct(public readonly Evaluation $evaluation)
    {
        $keys = array_map(
            static fn ($ruleEvaluation): string => $ruleEvaluation->rule()->key(),
            $evaluation->topApplicableEvaluations(),
        );

        parent::__construct(sprintf(
            'Several rules share the highest applicable priority: %s.',
            implode(', ', $keys),
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
