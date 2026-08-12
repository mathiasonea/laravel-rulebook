# Laravel Rulebook

**Keep historical business decisions reproducible—even after the rules change.**

Pricing, eligibility, and entitlement rules often change on a specific date. When that logic lives in growing `if` statements, it becomes difficult to answer:

- Which rule applied to this invoice, quote, or customer?
- Why did that rule win?
- Would the same decision be reproduced for that date?

Give each policy version a name and validity period. When resolution succeeds, Laravel Rulebook returns one explicit winner for that point in time:

```php
$decision = $vehiclePricingRulebook->resolveAt(
    subject: $vehicle,
    at: $invoice->issued_at,
    context: $pricingContext,
);

$decision->outcome()->formatted();            // 32.500,00 EUR
class_basename($decision->winningRule());     // AustrianElectricVehiclePrice2026
$decision->winningResult()->reason();         // The 2026 Austrian electric-vehicle price applies.
```

The same decision also explains what happened to every other rule:

| Rule | Status | Role |
| --- | --- | --- |
| Global default | `applicable` | Shadowed fallback |
| Austrian price | `applicable` | Shadowed fallback |
| Austrian EV 2025 | `outside_validity` | Skipped |
| Austrian EV 2026 | `applicable` | **Winner** |
| Austrian EV 2027 | `outside_validity` | Skipped |

**Change tomorrow's policy without rewriting yesterday's decision.**

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mathiasonea/laravel-rulebook.svg?style=flat-square)](https://packagist.org/packages/mathiasonea/laravel-rulebook)
[![Total Downloads](https://img.shields.io/packagist/dt/mathiasonea/laravel-rulebook.svg?style=flat-square)](https://packagist.org/packages/mathiasonea/laravel-rulebook)
[![Tests](https://github.com/mathiasonea/laravel-rulebook/actions/workflows/run-tests.yml/badge.svg?branch=main)](https://github.com/mathiasonea/laravel-rulebook/actions/workflows/run-tests.yml)
[![PHP Version](https://img.shields.io/packagist/dependency-v/mathiasonea/laravel-rulebook/php.svg?style=flat-square)](https://packagist.org/packages/mathiasonea/laravel-rulebook)
[![License](https://img.shields.io/packagist/l/mathiasonea/laravel-rulebook.svg?style=flat-square)](LICENSE.md)

## Before and after

Without an explicit model, effective-date logic tends to grow inside one branching path:

```php
if ($invoice->issued_at < new DateTimeImmutable('2026-01-01T00:00:00+01:00')) {
    return $this->priceUnder2025Policy($vehicle);
}

if ($invoice->issued_at < new DateTimeImmutable('2027-01-01T00:00:00+01:00')) {
    return $this->priceUnder2026Policy($vehicle);
}

return $this->currentPrice($vehicle);
```

With Rulebook, each policy version remains a named rule. The decision date is explicit, and the result says which rule won and why:

```php
$decision = $vehiclePricingRulebook->resolveAt(
    subject: $vehicle,
    at: $invoice->issued_at,
    context: $pricingContext,
);

$price = $decision->outcome();
$rule = $decision->winningRule();
$reason = $decision->winningResult()->reason();
```

Every resolution also retains the complete evaluation: applicable fallbacks, rejected rules, and rules skipped because they were outside their validity window.

## Installation

```bash
composer require mathiasonea/laravel-rulebook
```

Laravel discovers the package provider automatically. There is no configuration to publish, migration to run, facade, or global registry.

For a complete working application, run the [Austrian EV pricing example](https://github.com/mathiasonea/laravel-rulebook-austrian-ev-example). It compares three yearly policies and prints the winner, fallbacks, skipped validity windows, and reasons from one Artisan command.

## Use it when

- Effective-date conditionals keep accumulating in application services.
- A policy is edited in place even though old decisions must remain reproducible.
- Several rules can apply and the intended winner or fallback must be explicit.
- A decision varies by subject, context, and point in time.
- You need to reconstruct why an invoice, price, entitlement, or eligibility decision was made.

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13

## Not a fit when

Rulebook is deliberately code-defined and resolves exactly one winning rule. It is not a fit when you need:

- A DSL or rules authored in a database or UI.
- Workflow or state-machine behavior.
- Outcomes composed from multiple winning rules.

## Resources

- [Project page](https://mathiasonea.com/en/open-source/laravel-rulebook) — the permanent overview of Laravel Rulebook.
- [Architecture guide](https://mathiasonea.com/en/insights/versioned-business-rules-in-laravel) — a practical explanation of replacing dated conditionals with auditable, versioned rules.
- [Runnable Austrian EV example](https://github.com/mathiasonea/laravel-rulebook-austrian-ev-example) — a focused Laravel 12 application comparing the 2025, 2026, and 2027 policies, including fallbacks and skipped validity windows.

## Versioned pricing in practice

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

### Share stable policy, isolate yearly changes

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

### Resolve and inspect a dated decision

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
$decision->winningRuleKey();      // the stable key captured for the winner
$decision->winningResult();       // outcome and mandatory reason
$decision->evaluations();         // every RuleEvaluation
$decision->evaluationFor($key);   // one evaluation by stable rule key
$decision->applicableRules();     // includes lower-priority matches
$decision->inapplicableRules();
$decision->shadowedRules();       // applicable, but below the winner
$decision->shadowedEvaluations(); // rules, results, reasons, and captured metadata
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
$evaluation->shadowedEvaluations();
$evaluation->conflictingEvaluations();
$evaluation->evaluationFor($key);
$evaluation->hasWinner();
$evaluation->hasConflict();

$decision = $evaluation->resolve();
```

`resolveNow()` and `resolveAt()` are convenience methods for evaluating and then resolving.

Resolution throws:

- `NoMatchingRule` when nothing applies.
- `AmbiguousRuleMatch` when more than one applicable rule shares the highest priority.
- `DuplicateRuleKey` when registered rules expose the same key.
- `InvalidRuleKey` when a registered rule exposes a blank key.

`NoMatchingRule` and `AmbiguousRuleMatch` both retain the exact `Evaluation` on their public `$evaluation` property and through `evaluation()`. Registration order never breaks an equal-priority tie.

```php
use Illuminate\Support\Facades\Log;

try {
    $decision = $rulebook->resolveNow($vehicle, $context);
} catch (AmbiguousRuleMatch $exception) {
    foreach ($exception->evaluation->evaluations() as $ruleEvaluation) {
        Log::warning('Ambiguous rulebook evaluation.', [
            'rule' => $ruleEvaluation->key(),
            'applies' => $ruleEvaluation->isApplicable(),
            'reason' => $ruleEvaluation->result()->reason(),
        ]);
    }
}
```

## Rule authoring contract

Rulebook evaluates every rule inside its validity period, including lower-priority fallbacks, so that the returned evaluation explains the complete decision. Treat `evaluate()` as a deterministic, side-effect-free operation:

- Use `$input->at` instead of reading the current clock inside a rule.
- Do not send messages, write data, or trigger other side effects from `evaluate()`.
- Keep `key()`, `priority()`, and `validity()` stable for the lifetime of an evaluation.
- Make reasons safe and useful for logs or other diagnostic output.
- Let operational failures bubble; do not convert exceptions into domain-level rejections.
- Account for mutable dependencies: resolving an old date reproduces the policy represented by the currently deployed code and data, not necessarily the exact historical execution.

The default rule key is its class name. That is convenient while developing, but a class rename changes the key. Override `key()` with a stable domain identifier when decisions or their explanations are stored outside the current request:

```php
public function key(): string
{
    return 'austria.ev-price.2026';
}
```

Rulebook returns the complete evaluation but deliberately does not persist it. Applications that require a durable audit record can store a portable snapshot alongside their own business record.

## Structured statuses and reason codes

Every `RuleEvaluation` has one structured status:

- `RuleEvaluationStatus::Applicable` when the rule produced an applicable result.
- `RuleEvaluationStatus::DoesNotApply` when the rule was evaluated but did not apply.
- `RuleEvaluationStatus::OutsideValidity` when the rule was skipped because of its validity period.

`wasEvaluated()` remains available when only the distinction between a domain rejection and a skipped rule matters. Rule keys, priorities, and validity periods are captured once before domain evaluation, so later inspection cannot change the winner.

Results may also include an optional machine-readable reason code alongside the mandatory human explanation:

```php
return RuleResult::doesNotApply(
    reason: 'The vehicle is not electric.',
    reasonCode: 'vehicle_not_electric',
);
```

Use reason codes for stable filtering, metrics, or localization; keep the reason useful to a human reading the decision.

## Portable decision snapshots

Snapshots remove live subject, context, and rule objects while retaining the decision time, frozen rule metadata, validity windows, statuses, reasons, and reason codes. A decision snapshot always has one winner; an evaluation snapshot can instead record a conflict or no match. Both implement `JsonSerializable` and expose `toArray()`:

```php
$snapshot = $decision->snapshot(
    normalizeOutcome: static fn (Money $money): array => [
        'currency' => $money->currency,
        'amount_in_cents' => $money->cents,
    ],
);

$record = $snapshot->toArray();
$json = json_encode($snapshot, JSON_THROW_ON_ERROR);
```

Scalar, array, backed-enum, or `JsonSerializable` outcomes can use `$decision->snapshot()` directly. Supply `normalizeOutcome` when another outcome object needs an application-specific portable representation. Snapshot creation eagerly copies the normalized value and throws `UnportableSnapshotValue` for unsupported objects, resources, invalid UTF-8, non-finite numbers, cycles, or excessive nesting.

An evaluation can be snapshotted before resolution, including when no rule applies or several rules conflict:

```php
$snapshot = $rulebook->evaluateAt($vehicle, $at, $context)->snapshot();

$snapshot->winningRuleKey();       // string|null
$snapshot->conflictingRuleKeys();  // list<string>
$snapshot->evaluations();          // list<RuleEvaluationSnapshot>
```

Snapshots are transport records, not persistence. The application remains responsible for choosing where to store them and which subject or business-record identifier belongs beside them.

Every top-level snapshot contains `schema_version: 1`. The serialized field names, meanings, and status values are public API and follow the package's semantic-versioning policy.

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

- First-party temporal test matrices and expressive decision assertions
- Inspection tooling for keys, priorities, validity windows, gaps, and obvious collisions
- Optional Laravel integrations for observing or persisting snapshots
- Further extension points driven by concrete production use cases

Already maintaining effective-date business logic? Model one real decision with Rulebook, then [open an issue](https://github.com/mathiasonea/laravel-rulebook/issues/new/choose) or start a [discussion](https://github.com/mathiasonea/laravel-rulebook/discussions) and tell us where the API feels heavy or breaks down. Focused [pull requests](https://github.com/mathiasonea/laravel-rulebook/pulls) are welcome; please discuss larger changes first.

## Core invariants

Blank reasons, blank provided reason codes, blank rule keys, and invalid or empty validity periods are rejected. Skipped out-of-window rules remain distinguishable from evaluated domain rejections, and rule metadata is captured once per evaluation. Portable snapshots reject values that cannot be represented safely in their versioned JSON schema.

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
