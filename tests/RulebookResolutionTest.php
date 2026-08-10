<?php

use Carbon\CarbonImmutable;
use MathiasOnea\Rulebook\Evaluations\RuleEvaluation;
use MathiasOnea\Rulebook\Exceptions\AmbiguousRuleMatch;
use MathiasOnea\Rulebook\Exceptions\DuplicateRuleKey;
use MathiasOnea\Rulebook\Exceptions\InvalidRuleKey;
use MathiasOnea\Rulebook\Exceptions\NoMatchingRule;
use MathiasOnea\Rulebook\Resolution\RuleResolver;
use MathiasOnea\Rulebook\Results\RuleResult;
use MathiasOnea\Rulebook\Tests\Fixtures\AlwaysApplicableRule;
use MathiasOnea\Rulebook\Tests\Fixtures\BlankKeyRule;
use MathiasOnea\Rulebook\Tests\Fixtures\ConfigurableRulebook;
use MathiasOnea\Rulebook\Tests\Fixtures\ContainerInjectedRule;
use MathiasOnea\Rulebook\Tests\Fixtures\ContextReadingRule;
use MathiasOnea\Rulebook\Tests\Fixtures\DuplicateKeyOneRule;
use MathiasOnea\Rulebook\Tests\Fixtures\DuplicateKeyTwoRule;
use MathiasOnea\Rulebook\Tests\Fixtures\EqualPriorityRule;
use MathiasOnea\Rulebook\Tests\Fixtures\EvaluationProbe;
use MathiasOnea\Rulebook\Tests\Fixtures\ExplodingRule;
use MathiasOnea\Rulebook\Tests\Fixtures\HigherPriorityRule;
use MathiasOnea\Rulebook\Tests\Fixtures\InapplicableRule;
use MathiasOnea\Rulebook\Tests\Fixtures\InjectedOutcomeSource;
use MathiasOnea\Rulebook\Tests\Fixtures\MutableMetadataRule;
use MathiasOnea\Rulebook\Tests\Fixtures\NullableOutcomeRule;
use MathiasOnea\Rulebook\Tests\Fixtures\TestContext;
use MathiasOnea\Rulebook\Tests\Fixtures\TestSubject;
use MathiasOnea\Rulebook\Tests\Fixtures\WindowedRule;

afterEach(fn () => CarbonImmutable::setTestNow());

function rulebookWith(array $rules): ConfigurableRulebook
{
    return new ConfigurableRulebook(app(RuleResolver::class), $rules);
}

it('resolves one applicable rule into a complete decision', function () {
    $at = new DateTimeImmutable('2026-06-01T12:00:00+02:00');
    $decision = rulebookWith([AlwaysApplicableRule::class])->resolveAt(new TestSubject, $at);

    expect($decision->outcome())->toBe('default')
        ->and($decision->winningRule())->toBeInstanceOf(AlwaysApplicableRule::class)
        ->and($decision->winningResult()->reason())->toBe('The default rule covers every subject.')
        ->and($decision->evaluation()->winningEvaluation()?->rule())->toBe($decision->winningRule())
        ->and($decision->evaluatedAt())->toEqual($at)
        ->and($decision->evaluations())->toHaveCount(1)
        ->and($decision->applicableRules())->toHaveCount(1)
        ->and($decision->inapplicableRules())->toBeEmpty()
        ->and($decision->shadowedRules())->toBeEmpty();
});

it('provides stable defaults from the root rule class', function () {
    $rule = new AlwaysApplicableRule;

    expect($rule->key())->toBe(AlwaysApplicableRule::class)
        ->and($rule->priority())->toBe(0)
        ->and($rule->validity()->contains(new DateTimeImmutable('1900-01-01')))->toBeTrue()
        ->and($rule->validity()->contains(new DateTimeImmutable('2999-01-01')))->toBeTrue();
});

it('selects the sole highest-priority applicable rule', function () {
    $decision = rulebookWith([
        HigherPriorityRule::class,
        AlwaysApplicableRule::class,
    ])->resolveNow(new TestSubject);

    expect($decision->outcome())->toBe('specific')
        ->and($decision->winningRule())->toBeInstanceOf(HigherPriorityRule::class)
        ->and($decision->applicableRules())->toHaveCount(2)
        ->and($decision->shadowedRules())->toHaveCount(1)
        ->and($decision->shadowedRules()[0])->toBeInstanceOf(AlwaysApplicableRule::class);
});

it('never uses registration order to break equal-priority ties', function (array $rules) {
    $evaluation = rulebookWith($rules)->evaluateNow(new TestSubject);

    expect($evaluation->hasWinner())->toBeFalse()
        ->and($evaluation->hasConflict())->toBeTrue()
        ->and($evaluation->topApplicableEvaluations())->toHaveCount(2)
        ->and(fn () => $evaluation->resolve())->toThrow(AmbiguousRuleMatch::class);
})->with([
    'original order' => [[HigherPriorityRule::class, EqualPriorityRule::class]],
    'reversed order' => [[EqualPriorityRule::class, HigherPriorityRule::class]],
]);

it('preserves the complete evaluation on an ambiguity error', function () {
    $evaluation = rulebookWith([
        AlwaysApplicableRule::class,
        HigherPriorityRule::class,
        EqualPriorityRule::class,
        InapplicableRule::class,
    ])->evaluateNow(new TestSubject);

    try {
        $evaluation->resolve();
    } catch (AmbiguousRuleMatch $exception) {
        expect($exception->evaluation())->toBe($evaluation)
            ->and($exception->evaluation->evaluations())->toHaveCount(4)
            ->and($exception->evaluation->applicableRules())->toHaveCount(3)
            ->and($exception->evaluation->inapplicableRules())->toHaveCount(1)
            ->and($exception->evaluation->shadowedRules())->toHaveCount(1);

        return;
    }

    test()->fail('AmbiguousRuleMatch was not thrown.');
});

it('preserves the complete evaluation when no rule matches', function () {
    $evaluation = rulebookWith([InapplicableRule::class])->evaluateNow(new TestSubject);

    expect($evaluation->hasWinner())->toBeFalse()
        ->and($evaluation->hasConflict())->toBeFalse();

    try {
        $evaluation->resolve();
    } catch (NoMatchingRule $exception) {
        expect($exception->evaluation())->toBe($evaluation)
            ->and($exception->evaluation->evaluations())->toHaveCount(1)
            ->and($exception->evaluation->inapplicableEvaluations()[0]->result()->reason())
            ->toBe('The subject does not meet this rule.');

        return;
    }

    test()->fail('NoMatchingRule was not thrown.');
});

it('reports no match for an empty rulebook', function () {
    $evaluation = rulebookWith([])->evaluateNow(new TestSubject);

    expect($evaluation->evaluations())->toBeEmpty()
        ->and(fn () => $evaluation->resolve())->toThrow(NoMatchingRule::class);
});

it('rejects duplicate stable keys before evaluating rules', function () {
    try {
        rulebookWith([
            DuplicateKeyOneRule::class,
            DuplicateKeyTwoRule::class,
        ])->evaluateNow(new TestSubject);
    } catch (DuplicateRuleKey $exception) {
        expect($exception->key)->toBe('duplicate')
            ->and($exception->firstRule)->toBe(DuplicateKeyOneRule::class)
            ->and($exception->duplicateRule)->toBe(DuplicateKeyTwoRule::class);

        return;
    }

    test()->fail('DuplicateRuleKey was not thrown.');
});

it('rejects blank rule keys before evaluating rules', function () {
    expect(fn () => rulebookWith([BlankKeyRule::class])->evaluateNow(new TestSubject))
        ->toThrow(InvalidRuleKey::class, BlankKeyRule::class);
});

it('captures rule metadata once before domain evaluation', function () {
    $rule = new MutableMetadataRule;
    app()->instance(MutableMetadataRule::class, $rule);

    $decision = rulebookWith([
        AlwaysApplicableRule::class,
        MutableMetadataRule::class,
    ])->resolveNow(new TestSubject);
    $evaluation = $decision->evaluationFor('mutable-1');
    $snapshot = $decision->snapshot();

    expect($decision->winningRule())->toBe($rule)
        ->and($decision->winningRuleKey())->toBe('mutable-1')
        ->and($evaluation)->not->toBeNull()
        ->and($evaluation?->priority())->toBe(99)
        ->and($evaluation?->validity()->startsAt())->toBeNull()
        ->and($evaluation?->validity()->endsAt())->toBeNull()
        ->and($snapshot->winningRuleKey())->toBe('mutable-1')
        ->and($rule->keyCalls)->toBe(1)
        ->and($rule->priorityCalls)->toBe(1)
        ->and($rule->validityCalls)->toBe(1);
});

it('rejects an applicable result for a rule that was not evaluated', function () {
    expect(fn () => new RuleEvaluation(
        rule: new AlwaysApplicableRule,
        result: RuleResult::applies('invalid', 'This is contradictory.'),
        evaluated: false,
    ))->toThrow(InvalidArgumentException::class, 'unevaluated rule');
});

it('does not invoke rules outside their validity period', function () {
    $probe = new EvaluationProbe;
    app()->instance(EvaluationProbe::class, $probe);

    $evaluation = rulebookWith([WindowedRule::class])->evaluateAt(
        new TestSubject,
        new DateTimeImmutable('2027-01-01T00:00:00+00:00'),
    );
    $ruleEvaluation = $evaluation->evaluations()[0];

    expect($probe->calls)->toBe(0)
        ->and($ruleEvaluation->wasEvaluated())->toBeFalse()
        ->and($ruleEvaluation->isInapplicable())->toBeTrue()
        ->and($ruleEvaluation->result()->reason())->toContain('not valid');
});

it('invokes a rule at its inclusive validity start', function () {
    $probe = new EvaluationProbe;
    app()->instance(EvaluationProbe::class, $probe);

    $decision = rulebookWith([WindowedRule::class])->resolveAt(
        new TestSubject,
        new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    expect($probe->calls)->toBe(1)
        ->and($decision->outcome())->toBe('windowed')
        ->and($decision->evaluations()[0]->wasEvaluated())->toBeTrue();
});

it("resolves every rule class through Laravel's container", function () {
    app()->instance(InjectedOutcomeSource::class, new InjectedOutcomeSource('injected'));

    $decision = rulebookWith([ContainerInjectedRule::class])->resolveNow(new TestSubject);

    expect($decision->outcome())->toBe('injected')
        ->and($decision->winningRule())->toBeInstanceOf(ContainerInjectedRule::class);
});

it('passes optional context into rules', function () {
    $decision = rulebookWith([ContextReadingRule::class])->resolveNow(
        new TestSubject,
        new TestContext('partner-api'),
    );

    expect($decision->outcome())->toBe('partner-api');
});

it('returns nullable outcomes without treating them as no match', function () {
    $decision = rulebookWith([NullableOutcomeRule::class])->resolveNow(new TestSubject);

    expect($decision->outcome())->toBeNull()
        ->and($decision->winningResult()->isApplicable())->toBeTrue();
});

it('uses Carbon test time for now resolutions', function () {
    CarbonImmutable::setTestNow('2026-08-15T12:34:56.123456+02:00');

    $decision = rulebookWith([AlwaysApplicableRule::class])->resolveNow(new TestSubject);

    expect($decision->evaluatedAt())
        ->toEqual(new DateTimeImmutable('2026-08-15T12:34:56.123456+02:00'));
});

it('lets exceptions from rule code bubble unchanged', function () {
    expect(fn () => rulebookWith([ExplodingRule::class])->evaluateNow(new TestSubject))
        ->toThrow(DomainException::class, 'Rule dependency failed.');
});
