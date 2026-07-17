# Laravel Rulebook

Select which business rule applies to an object at a given time—and understand why.

Laravel Rulebook is a small, Laravel-first package for decisions where exactly one rule must govern a subject. Your application defines the rulebook, rules, optional context, and outcome type. The package resolves rule dependencies through Laravel's container, applies explicit priorities and time windows, and retains the explanation from every rule it considers.

It is intentionally not a general-purpose rules engine.

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13

## Installation

```bash
composer require mathiasonea/laravel-rulebook
```

Laravel discovers the package provider automatically. There is no configuration to publish, migration to run, facade, or global registry.

## Define a rulebook

A rulebook is an application-owned class. Its PHPStan annotation establishes the subject, context, and outcome types for every resolution.

```php
namespace App\Pricing;

use App\Models\Vehicle;
use MathiasOnea\Rulebook\Rulebook;

/**
 * @extends Rulebook<Vehicle, VehiclePricingContext, Money>
 */
final class VehiclePricingRulebook extends Rulebook
{
    protected function rules(): array
    {
        return [
            DefaultVehiclePrice::class,
            AustrianVehiclePrice::class,
            AustrianElectricVehiclePrice::class,
        ];
    }
}
```

Rule order does not decide the winner. Every applicable rule participates in explicit priority resolution.

## Define rules

Extend the root `Rule` class and return a result with a mandatory explanation. A rule is valid forever and has priority `0` unless it overrides those defaults.

```php
namespace App\Pricing;

use App\Models\Vehicle;
use DateTimeImmutable;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Periods\ValidityPeriod;
use MathiasOnea\Rulebook\Results\RuleResult;
use MathiasOnea\Rulebook\Rule;

/**
 * @extends Rule<Vehicle, VehiclePricingContext, Money>
 */
final class AustrianElectricVehiclePrice extends Rule
{
    public function __construct(
        private ExchangeRates $exchangeRates,
    ) {}

    public function validity(): ValidityPeriod
    {
        return ValidityPeriod::between(
            from: new DateTimeImmutable('2026-01-01T00:00:00+01:00'),
            until: new DateTimeImmutable('2027-01-01T00:00:00+01:00'),
        );
    }

    public function priority(): int
    {
        return 100;
    }

    public function evaluate(RuleInput $input): RuleResult
    {
        $vehicle = $input->subject(Vehicle::class);
        $context = $input->context(VehiclePricingContext::class);

        if (! $vehicle->isElectric()) {
            return RuleResult::doesNotApply(
                reason: 'The vehicle is not electric.',
            );
        }

        if ($context->country !== Country::Austria) {
            return RuleResult::doesNotApply(
                reason: 'The pricing country is not Austria.',
            );
        }

        return RuleResult::applies(
            outcome: Money::EUR(32_500_00),
            reason: 'The 2026 Austrian electric-vehicle price applies.',
        );
    }
}
```

The rule class string is resolved through Laravel's container, so constructor injection works without package-specific registration. Exceptions from a rule or one of its dependencies bubble unchanged; an operational failure is never converted into “does not apply.”

## Resolve a decision

Inject the application-defined rulebook wherever the decision is needed:

```php
final class CalculateVehiclePrice
{
    public function __construct(
        private VehiclePricingRulebook $rulebook,
    ) {}

    public function execute(
        Vehicle $vehicle,
        VehiclePricingContext $context,
    ): Money {
        return $this->rulebook
            ->resolveNow($vehicle, $context)
            ->outcome();
    }
}
```

Resolve historical or future decisions with a `DateTimeInterface`:

```php
$decision = $rulebook->resolveAt(
    subject: $vehicle,
    at: $invoice->issued_at,
    context: $context,
);
```

The returned decision exposes the winner and the complete evaluation:

```php
$decision->outcome();             // Money
$decision->winningRule();         // the selected Rule instance
$decision->winningResult();       // outcome and mandatory reason
$decision->evaluations();         // every RuleEvaluation
$decision->applicableRules();     // includes lower-priority matches
$decision->inapplicableRules();
$decision->shadowedRules();       // applicable, but below the winner
$decision->evaluatedAt();
```

A lower-priority match is still applicable. It is described as shadowed because the sole higher-priority rule governs the decision.

## Evaluate without requiring a winner

Use `evaluateNow()` or `evaluateAt()` when diagnostics must remain available even if no rule applies or the top priority is ambiguous.

```php
$evaluation = $rulebook->evaluateNow($vehicle, $context);

$evaluation->evaluations();
$evaluation->applicableEvaluations();
$evaluation->inapplicableEvaluations();
$evaluation->applicableRules();
$evaluation->inapplicableRules();
$evaluation->shadowedRules();
$evaluation->hasWinner();
$evaluation->hasConflict();

$decision = $evaluation->resolve();
```

`resolveNow()` and `resolveAt()` are convenience methods for evaluating and then resolving.

Resolution throws:

- `NoMatchingRule` when nothing applies.
- `AmbiguousRuleMatch` when more than one applicable rule shares the highest priority.
- `DuplicateRuleKey` when registered rules expose the same key.

`NoMatchingRule` and `AmbiguousRuleMatch` both retain the exact `Evaluation` on their public `$evaluation` property and through `evaluation()`. Registration order never breaks an equal-priority tie.

```php
try {
    $decision = $rulebook->resolveNow($vehicle, $context);
} catch (AmbiguousRuleMatch $exception) {
    foreach ($exception->evaluation->evaluations() as $ruleEvaluation) {
        report([
            'rule' => $ruleEvaluation->rule()->key(),
            'applies' => $ruleEvaluation->isApplicable(),
            'reason' => $ruleEvaluation->result()->reason(),
        ]);
    }
}
```

## Validity periods

```php
ValidityPeriod::always();
ValidityPeriod::from($startsAt);
ValidityPeriod::until($endsAt);
ValidityPeriod::between(from: $startsAt, until: $endsAt);
```

Periods are half-open: `[from, until)`.

- `from` is inclusive.
- `until` is exclusive.
- Open starts and ends are supported.
- Empty and reversed periods throw `InvalidValidityPeriod`.
- Inputs are copied into immutable date-time values.
- Comparisons use absolute instants; timezones are never silently rewritten.

An out-of-window rule is not invoked. Its `RuleEvaluation` is inapplicable, has a generated validity reason, and returns `false` from `wasEvaluated()`.

## Optional context

Use `null` as the context type when a decision only needs its subject:

```php
/**
 * @extends Rulebook<Subscription, null, BillingTerms>
 */
final class SubscriptionBillingRulebook extends Rulebook
{
    protected function rules(): array
    {
        return [
            StandardSubscriptionBilling::class,
            LegacySubscriptionBilling::class,
        ];
    }
}

$terms = $rulebook->resolveNow($subscription)->outcome();
```

`RuleInput::subject()` and `RuleInput::context()` provide typed access. A mismatch throws `UnexpectedSubject` or `UnexpectedContext` with the expected and actual types.

## Nullable outcomes

`null` can be a valid typed outcome:

```php
return RuleResult::applies(
    outcome: null,
    reason: 'No charge is the selected billing outcome.',
);
```

Applicability is stored separately from the outcome, so this is not confused with `RuleResult::doesNotApply(...)`.

## Time and testing

“Now” resolutions use Carbon's clock, including `CarbonImmutable::setTestNow()` in tests. Explicit `DateTimeInterface` values passed to `resolveAt()` and `evaluateAt()` are copied without changing their instant.

## Deliberate non-goals

Version 0.1 does not provide:

- Database, JSON, YAML, or UI-authored rules
- Persistence, migrations, caching, events, queues, or auto-discovery
- An expression language or arbitrary-code evaluator
- Automatic specificity scoring
- Multi-rule outcome composition, pipelines, discounts, or transformations
- A facade, global registry, or publishable configuration
- Replacements for Laravel validation, policies, or gates

If a decision needs several outcomes to be combined in sequence, model that workflow outside Rulebook or use a pipeline-oriented abstraction.

## Implementation clarifications

The v0.1 implementation makes three invariants explicit at runtime: blank or whitespace-only reasons are rejected; an invalid or empty validity period is rejected when constructed; and skipped out-of-window rules are distinguishable from evaluated domain rejections through `RuleEvaluation::wasEvaluated()`.

## Development

```bash
composer validate --strict
composer format-check
composer analyse
composer test
```

The test suite includes vehicle-pricing and subscription-billing rulebooks, boundary and timezone cases, conflict and missing-match inspection, container injection, nullable outcomes, Carbon test time, exception propagation, architecture constraints, and PHPStan outcome inference.

## License

Laravel Rulebook is open-source software licensed under the [MIT license](LICENSE.md).
