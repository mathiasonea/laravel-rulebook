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

## A versioned pricing example

Suppose an Austrian electric-vehicle price changes every calendar year. The base price, incentive, and battery fee can all change, while a general Austrian price and a global default must remain available as fallbacks.

Model each policy version as its own rule. The rulebook then becomes a readable history of every policy that can govern the decision:

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
            AustrianElectricVehiclePrice2025::class,
            AustrianElectricVehiclePrice2026::class,
            AustrianElectricVehiclePrice2027::class,
        ];
    }
}
```

The PHPStan annotation establishes the subject, context, and outcome types for every rule in the rulebook. Rule order does not decide the winner; every applicable rule participates in explicit priority resolution.

## Share stable policy, isolate yearly changes

Common eligibility can live in an abstract application-owned rule. The calculation delegates the values that are expected to change to the concrete yearly policy:

```php
namespace App\Pricing;

use App\Models\Vehicle;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Results\RuleResult;
use MathiasOnea\Rulebook\Rule;

/**
 * @extends Rule<Vehicle, VehiclePricingContext, Money>
 */
abstract class AustrianElectricVehiclePrice extends Rule
{
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
            outcome: Money::EUR(
                $this->basePriceInCents()
                - $this->incentiveInCents()
                + ($vehicle->batteryCapacityInKwh * $this->batteryFeePerKwhInCents()),
            ),
            reason: "The {$this->policyYear()} Austrian electric-vehicle price applies.",
        );
    }

    abstract protected function policyYear(): int;

    abstract protected function basePriceInCents(): int;

    abstract protected function incentiveInCents(): int;

    abstract protected function batteryFeePerKwhInCents(): int;
}
```

Each year supplies its own validity window and parameters:

```php
use DateTimeImmutable;
use MathiasOnea\Rulebook\Periods\ValidityPeriod;

final class AustrianElectricVehiclePrice2025 extends AustrianElectricVehiclePrice
{
    public function validity(): ValidityPeriod
    {
        return ValidityPeriod::between(
            from: new DateTimeImmutable('2025-01-01T00:00:00+01:00'),
            until: new DateTimeImmutable('2026-01-01T00:00:00+01:00'),
        );
    }

    protected function policyYear(): int { return 2025; }
    protected function basePriceInCents(): int { return 35_000_00; }
    protected function incentiveInCents(): int { return 4_000_00; }
    protected function batteryFeePerKwhInCents(): int { return 0; }
}

final class AustrianElectricVehiclePrice2026 extends AustrianElectricVehiclePrice
{
    public function validity(): ValidityPeriod
    {
        return ValidityPeriod::between(
            from: new DateTimeImmutable('2026-01-01T00:00:00+01:00'),
            until: new DateTimeImmutable('2027-01-01T00:00:00+01:00'),
        );
    }

    protected function policyYear(): int { return 2026; }
    protected function basePriceInCents(): int { return 35_000_00; }
    protected function incentiveInCents(): int { return 2_800_00; }
    protected function batteryFeePerKwhInCents(): int { return 4_00; }
}

final class AustrianElectricVehiclePrice2027 extends AustrianElectricVehiclePrice
{
    public function validity(): ValidityPeriod
    {
        return ValidityPeriod::between(
            from: new DateTimeImmutable('2027-01-01T00:00:00+01:00'),
            until: new DateTimeImmutable('2028-01-01T00:00:00+01:00'),
        );
    }

    protected function policyYear(): int { return 2027; }
    protected function basePriceInCents(): int { return 35_500_00; }
    protected function incentiveInCents(): int { return 1_000_00; }
    protected function batteryFeePerKwhInCents(): int { return 5_00; }
}
```

This keeps a historical policy intact after a new year begins. If the formula itself changes in 2027—not just its parameters—the 2027 class can override the calculation without adding `if ($year === ...)` branches to older rules.

The rule class strings are resolved through Laravel's container, so the shared rule or concrete yearly rules can use constructor injection without package-specific registration. Exceptions from a rule or one of its dependencies bubble unchanged; an operational failure is never converted into “does not apply.”

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
    subject: new Vehicle(
        electric: true,
        batteryCapacityInKwh: 75,
    ),
    at: new DateTimeImmutable('2026-06-15T10:00:00+02:00'),
    context: new VehiclePricingContext(country: Country::Austria),
);

$decision->winningRule();          // an AustrianElectricVehiclePrice2026 instance
$decision->outcome();              // EUR 32,500.00
$decision->winningResult()->reason();
// "The 2026 Austrian electric-vehicle price applies."
```

At that instant, the 2025 and 2027 rules are outside their validity windows and are not invoked. The 2026 rule wins with priority `100`; the general Austrian and default prices can still be inspected as applicable but shadowed fallbacks.

| Rule | What happens on 2026-06-15 | Role in the decision |
| --- | --- | --- |
| `DefaultVehiclePrice` | Applies | Shadowed fallback |
| `AustrianVehiclePrice` | Applies | Shadowed fallback |
| `AustrianElectricVehiclePrice2025` | Outside its validity window; not invoked | Inapplicable |
| `AustrianElectricVehiclePrice2026` | Applies | Winner |
| `AustrianElectricVehiclePrice2027` | Outside its validity window; not invoked | Inapplicable |

The same rulebook can reproduce decisions under earlier or later policy versions without changing application code:

```php
$rulebook->resolveAt($vehicle, new DateTimeImmutable('2025-07-01T00:00:00+02:00'), $context)
    ->winningRule(); // AustrianElectricVehiclePrice2025

$rulebook->resolveAt($vehicle, new DateTimeImmutable('2027-07-01T00:00:00+02:00'), $context)
    ->winningRule(); // AustrianElectricVehiclePrice2027
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

## Roadmap

- Database, JSON, YAML, or UI-authored rules
- Optional persistence, caching, events, queues, or rule auto-discovery
- A constrained expression language for rules that should not be PHP classes
- Alternative specificity or conflict-resolution strategies
- Multi-rule outcome composition, pipelines, discounts, or transformations
- Convenience integrations such as a facade, registry, or publishable configuration

Have a concrete use case? [Open an issue](https://github.com/mathiasonea/laravel-rulebook/issues/new/choose) and describe the decision you are modelling, the constraints involved, and where the current API falls short. Focused [pull requests](https://github.com/mathiasonea/laravel-rulebook/pulls) are welcome; please start with an issue or [discussion](https://github.com/mathiasonea/laravel-rulebook/discussions) before investing in a larger change.

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
