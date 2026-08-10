<?php

namespace MathiasOnea\Rulebook\Resolution;

use Illuminate\Contracts\Container\Container;
use MathiasOnea\Rulebook\Contracts\Rule;
use MathiasOnea\Rulebook\Evaluations\Evaluation;
use MathiasOnea\Rulebook\Evaluations\RuleEvaluation;
use MathiasOnea\Rulebook\Exceptions\DuplicateRuleKey;
use MathiasOnea\Rulebook\Exceptions\InvalidRuleKey;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Periods\ValidityPeriod;
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

        foreach ($rules as $resolvedRule) {
            $rule = $resolvedRule['rule'];

            if (! $resolvedRule['validity']->contains($input->at)) {
                $evaluations[] = new RuleEvaluation(
                    rule: $rule,
                    result: RuleResult::doesNotApply(sprintf(
                        'The rule is not valid at %s.',
                        $input->at->format('Y-m-d\TH:i:s.uP'),
                    )),
                    evaluated: false,
                    key: $resolvedRule['key'],
                    priority: $resolvedRule['priority'],
                    validity: $resolvedRule['validity'],
                );

                continue;
            }

            $evaluations[] = new RuleEvaluation(
                rule: $rule,
                result: $rule->evaluate($input),
                evaluated: true,
                key: $resolvedRule['key'],
                priority: $resolvedRule['priority'],
                validity: $resolvedRule['validity'],
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
     * @return list<array{
     *     rule: Rule<TSubject, TContext, TOutcome>,
     *     key: string,
     *     priority: int,
     *     validity: ValidityPeriod
     * }>
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

            if (trim($key) === '') {
                throw new InvalidRuleKey($resolved::class);
            }

            if (array_key_exists($key, $rulesByKey)) {
                throw new DuplicateRuleKey(
                    key: $key,
                    firstRule: $rulesByKey[$key]::class,
                    duplicateRule: $resolved::class,
                );
            }

            $rulesByKey[$key] = $resolved;
            $rules[] = [
                'rule' => $resolved,
                'key' => $key,
                'priority' => $resolved->priority(),
                'validity' => $resolved->validity(),
            ];
        }

        return $rules;
    }
}
