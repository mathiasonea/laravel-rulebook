<?php

namespace MathiasOnea\Rulebook\Tests\Fixtures;

use DateTimeImmutable;
use DomainException;
use MathiasOnea\Rulebook\Contracts\Rule as RuleContract;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Periods\ValidityPeriod;
use MathiasOnea\Rulebook\Resolution\RuleResolver;
use MathiasOnea\Rulebook\Results\RuleResult;
use MathiasOnea\Rulebook\Rule;
use MathiasOnea\Rulebook\Rulebook;

final readonly class TestSubject
{
    public function __construct(public string $name = 'subject') {}
}

final readonly class OtherSubject {}

final readonly class TestContext
{
    public function __construct(public string $channel = 'web') {}
}

final readonly class OtherContext {}

/** @extends Rulebook<TestSubject, TestContext|null, mixed> */
final class ConfigurableRulebook extends Rulebook
{
    /**
     * @param  list<class-string<RuleContract<TestSubject, TestContext|null, mixed>>>  $registeredRules
     */
    public function __construct(
        RuleResolver $resolver,
        private readonly array $registeredRules,
    ) {
        parent::__construct($resolver);
    }

    /**
     * @return list<class-string<RuleContract<TestSubject, TestContext|null, mixed>>>
     */
    protected function rules(): array
    {
        return $this->registeredRules;
    }
}

/** @extends Rule<TestSubject, TestContext|null, string> */
final class AlwaysApplicableRule extends Rule
{
    public function evaluate(RuleInput $input): RuleResult
    {
        return RuleResult::applies('default', 'The default rule covers every subject.');
    }
}

/** @extends Rule<TestSubject, TestContext|null, string> */
final class HigherPriorityRule extends Rule
{
    public function priority(): int
    {
        return 100;
    }

    public function evaluate(RuleInput $input): RuleResult
    {
        return RuleResult::applies('specific', 'The specific rule has priority.');
    }
}

/** @extends Rule<TestSubject, TestContext|null, string> */
final class EqualPriorityRule extends Rule
{
    public function priority(): int
    {
        return 100;
    }

    public function evaluate(RuleInput $input): RuleResult
    {
        return RuleResult::applies('also-specific', 'Another specific rule also applies.');
    }
}

/** @extends Rule<TestSubject, TestContext|null, string> */
final class InapplicableRule extends Rule
{
    public function evaluate(RuleInput $input): RuleResult
    {
        return RuleResult::doesNotApply(
            reason: 'The subject does not meet this rule.',
            reasonCode: 'subject_ineligible',
        );
    }
}

/** @extends Rule<TestSubject, TestContext|null, string> */
final class BlankKeyRule extends Rule
{
    public function key(): string
    {
        return '   ';
    }

    public function evaluate(RuleInput $input): RuleResult
    {
        return RuleResult::applies('blank-key', 'This rule should never be evaluated.');
    }
}

/** @extends Rule<TestSubject, TestContext|null, string> */
final class MutableMetadataRule extends Rule
{
    public int $keyCalls = 0;

    public int $priorityCalls = 0;

    public int $validityCalls = 0;

    public function key(): string
    {
        $this->keyCalls++;

        return 'mutable-'.$this->keyCalls;
    }

    public function priority(): int
    {
        $this->priorityCalls++;

        return 100 - $this->priorityCalls;
    }

    public function validity(): ValidityPeriod
    {
        $this->validityCalls++;

        return $this->validityCalls === 1
            ? ValidityPeriod::always()
            : ValidityPeriod::until(new DateTimeImmutable('2000-01-01T00:00:00+00:00'));
    }

    public function evaluate(RuleInput $input): RuleResult
    {
        return RuleResult::applies('mutable', 'The captured metadata remains stable.');
    }
}

/** @extends Rule<TestSubject, TestContext|null, string> */
final class ContextReadingRule extends Rule
{
    public function evaluate(RuleInput $input): RuleResult
    {
        $context = $input->context(TestContext::class);

        return RuleResult::applies($context->channel, 'The supplied channel is the outcome.');
    }
}

/** @extends Rule<TestSubject, TestContext|null, null> */
final class NullableOutcomeRule extends Rule
{
    public function evaluate(RuleInput $input): RuleResult
    {
        return RuleResult::applies(null, 'A null outcome is meaningful for this rule.');
    }
}

/** @extends Rule<TestSubject, TestContext|null, string> */
final class DuplicateKeyOneRule extends Rule
{
    public function key(): string
    {
        return 'duplicate';
    }

    public function evaluate(RuleInput $input): RuleResult
    {
        return RuleResult::applies('one', 'The first duplicate applies.');
    }
}

/** @extends Rule<TestSubject, TestContext|null, string> */
final class DuplicateKeyTwoRule extends Rule
{
    public function key(): string
    {
        return 'duplicate';
    }

    public function evaluate(RuleInput $input): RuleResult
    {
        return RuleResult::applies('two', 'The second duplicate applies.');
    }
}

final class EvaluationProbe
{
    public int $calls = 0;
}

/** @extends Rule<TestSubject, TestContext|null, string> */
final class WindowedRule extends Rule
{
    public function __construct(private readonly EvaluationProbe $probe) {}

    public function validity(): ValidityPeriod
    {
        return ValidityPeriod::between(
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2027-01-01T00:00:00+00:00'),
        );
    }

    public function evaluate(RuleInput $input): RuleResult
    {
        $this->probe->calls++;

        return RuleResult::applies('windowed', 'The rule is inside its validity window.');
    }
}

final readonly class InjectedOutcomeSource
{
    public function __construct(private string $outcome) {}

    public function outcome(): string
    {
        return $this->outcome;
    }
}

/** @extends Rule<TestSubject, TestContext|null, string> */
final class ContainerInjectedRule extends Rule
{
    public function __construct(private readonly InjectedOutcomeSource $source) {}

    public function evaluate(RuleInput $input): RuleResult
    {
        return RuleResult::applies(
            $this->source->outcome(),
            'The outcome came from a container-injected dependency.',
        );
    }
}

/** @extends Rule<TestSubject, TestContext|null, string> */
final class ExplodingRule extends Rule
{
    public function evaluate(RuleInput $input): RuleResult
    {
        throw new DomainException('Rule dependency failed.');
    }
}
