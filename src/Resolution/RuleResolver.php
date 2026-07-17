<?php

namespace MathiasOnea\Rulebook\Resolution;

use Illuminate\Contracts\Container\Container;
use MathiasOnea\Rulebook\Contracts\Rule;
use MathiasOnea\Rulebook\Evaluations\Evaluation;
use MathiasOnea\Rulebook\Evaluations\RuleEvaluation;
use MathiasOnea\Rulebook\Exceptions\DuplicateRuleKey;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Results\RuleResult;
use UnexpectedValueException;

final readonly class RuleResolver
{
    public function __construct(private Container $container) {}

    /**
     * @template TSubject of object
     * @template TContext of object|null
     * @template TOutcome
     *
     * @param  list<class-string<Rule<TSubject, TContext, TOutcome>>>  $ruleClasses
     * @param  RuleInput<TSubject, TContext>  $input
     * @return Evaluation<TSubject, TContext, TOutcome>
     */
    public function evaluate(array $ruleClasses, RuleInput $input): Evaluation
    {
        $rules = $this->resolveRules($ruleClasses);
        $evaluations = [];

        foreach ($rules as $rule) {
            if (! $rule->validity()->contains($input->at)) {
                $evaluations[] = new RuleEvaluation(
                    rule: $rule,
                    result: RuleResult::doesNotApply(sprintf(
                        'The rule is not valid at %s.',
                        $input->at->format('Y-m-d\TH:i:s.uP'),
                    )),
                    evaluated: false,
                );

                continue;
            }

            $evaluations[] = new RuleEvaluation(
                rule: $rule,
                result: $rule->evaluate($input),
                evaluated: true,
            );
        }

        return new Evaluation($input, $evaluations);
    }

    /**
     * @template TSubject of object
     * @template TContext of object|null
     * @template TOutcome
     *
     * @param  list<class-string<Rule<TSubject, TContext, TOutcome>>>  $ruleClasses
     * @return list<Rule<TSubject, TContext, TOutcome>>
     */
    private function resolveRules(array $ruleClasses): array
    {
        $rules = [];
        $rulesByKey = [];

        foreach ($ruleClasses as $ruleClass) {
            $resolved = $this->container->make($ruleClass);

            if (! $resolved instanceof Rule) {
                throw new UnexpectedValueException(sprintf(
                    'Container resolution for [%s] must return an implementation of [%s]; received [%s].',
                    $ruleClass,
                    Rule::class,
                    get_debug_type($resolved),
                ));
            }

            /** @var Rule<TSubject, TContext, TOutcome> $resolved */
            $key = $resolved->key();

            if (array_key_exists($key, $rulesByKey)) {
                throw new DuplicateRuleKey(
                    key: $key,
                    firstRule: $rulesByKey[$key]::class,
                    duplicateRule: $resolved::class,
                );
            }

            $rulesByKey[$key] = $resolved;
            $rules[] = $resolved;
        }

        return $rules;
    }
}
