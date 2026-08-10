<?php

use MathiasOnea\Rulebook\Exceptions\UnportableSnapshotValue;
use MathiasOnea\Rulebook\Tests\Fixtures\VehiclePricing\AustrianElectricVehiclePrice;
use MathiasOnea\Rulebook\Tests\Fixtures\VehiclePricing\AustrianVehiclePrice;
use MathiasOnea\Rulebook\Tests\Fixtures\VehiclePricing\DefaultVehiclePrice;
use MathiasOnea\Rulebook\Tests\Fixtures\VehiclePricing\Money;
use MathiasOnea\Rulebook\Tests\Fixtures\VehiclePricing\Vehicle;
use MathiasOnea\Rulebook\Tests\Fixtures\VehiclePricing\VehiclePricingContext;
use MathiasOnea\Rulebook\Tests\Fixtures\VehiclePricing\VehiclePricingRulebook;

it('selects a time-bound Austrian electric vehicle price while retaining fallbacks', function () {
    $decision = app(VehiclePricingRulebook::class)->resolveAt(
        subject: new Vehicle(electric: true),
        at: new DateTimeImmutable('2026-06-15T10:00:00+02:00'),
        context: new VehiclePricingContext(country: 'AT'),
    );

    expect($decision->outcome()->cents)->toBe(32_500_00)
        ->and($decision->winningRule())->toBeInstanceOf(AustrianElectricVehiclePrice::class)
        ->and($decision->applicableRules())->toHaveCount(3)
        ->and($decision->shadowedRules())->toHaveCount(2)
        ->and($decision->shadowedRules()[0])->toBeInstanceOf(DefaultVehiclePrice::class)
        ->and($decision->shadowedRules()[1])->toBeInstanceOf(AustrianVehiclePrice::class);
});

it('falls back at the exclusive end of the electric price period', function () {
    $decision = app(VehiclePricingRulebook::class)->resolveAt(
        subject: new Vehicle(electric: true),
        at: new DateTimeImmutable('2027-01-01T00:00:00+01:00'),
        context: new VehiclePricingContext(country: 'AT'),
    );

    expect($decision->outcome()->cents)->toBe(34_000_00)
        ->and($decision->winningRule())->toBeInstanceOf(AustrianVehiclePrice::class)
        ->and($decision->inapplicableRules())->toHaveCount(1)
        ->and($decision->inapplicableRules()[0])->toBeInstanceOf(AustrianElectricVehiclePrice::class)
        ->and($decision->evaluations()[2]->wasEvaluated())->toBeFalse();
});

it('retains a domain reason when a valid specialized price does not apply', function () {
    $decision = app(VehiclePricingRulebook::class)->resolveAt(
        subject: new Vehicle(electric: false),
        at: new DateTimeImmutable('2026-06-15T10:00:00+02:00'),
        context: new VehiclePricingContext(country: 'AT'),
    );

    expect($decision->winningRule())->toBeInstanceOf(AustrianVehiclePrice::class)
        ->and($decision->evaluations()[2]->wasEvaluated())->toBeTrue()
        ->and($decision->evaluations()[2]->result()->reason())->toBe('The vehicle is not electric.');
});

it('uses the default price outside Austria', function () {
    $decision = app(VehiclePricingRulebook::class)->resolveAt(
        subject: new Vehicle(electric: true),
        at: new DateTimeImmutable('2026-06-15T10:00:00+02:00'),
        context: new VehiclePricingContext(country: 'DE'),
    );

    expect($decision->outcome()->cents)->toBe(30_000_00)
        ->and($decision->winningRule())->toBeInstanceOf(DefaultVehiclePrice::class)
        ->and($decision->inapplicableRules())->toHaveCount(2);
});

it('normalizes an object outcome when creating a decision snapshot', function () {
    $decision = app(VehiclePricingRulebook::class)->resolveAt(
        subject: new Vehicle(electric: true),
        at: new DateTimeImmutable('2026-06-15T10:00:00+02:00'),
        context: new VehiclePricingContext(country: 'AT'),
    );

    $snapshot = $decision->snapshot(
        static fn (Money $money): array => ['cents' => $money->cents],
    );

    expect($snapshot->outcome())->toBe(['cents' => 32_500_00])
        ->and($snapshot->toArray()['outcome'])->toBe(['cents' => 32_500_00])
        ->and(json_decode(json_encode($snapshot, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR)['outcome'])
        ->toBe(['cents' => 32_500_00]);
});

it('rejects an object outcome without an explicit portable representation', function () {
    $decision = app(VehiclePricingRulebook::class)->resolveAt(
        subject: new Vehicle(electric: true),
        at: new DateTimeImmutable('2026-06-15T10:00:00+02:00'),
        context: new VehiclePricingContext(country: 'AT'),
    );

    expect(fn () => $decision->snapshot())
        ->toThrow(UnportableSnapshotValue::class, Money::class)
        ->and(fn () => $decision->snapshot(static fn (Money $money): Money => $money))
        ->toThrow(UnportableSnapshotValue::class, Money::class);
});
