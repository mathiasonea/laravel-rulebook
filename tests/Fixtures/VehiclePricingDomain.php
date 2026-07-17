<?php

namespace MathiasOnea\Rulebook\Tests\Fixtures\VehiclePricing;

use DateTimeImmutable;
use MathiasOnea\Rulebook\Contracts\Rule as RuleContract;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Periods\ValidityPeriod;
use MathiasOnea\Rulebook\Results\RuleResult;
use MathiasOnea\Rulebook\Rule;
use MathiasOnea\Rulebook\Rulebook;

final readonly class Money
{
    public function __construct(
        public int $cents,
        public string $currency = 'EUR',
    ) {}
}

final readonly class Vehicle
{
    public function __construct(public bool $electric) {}
}

final readonly class VehiclePricingContext
{
    public function __construct(public string $country) {}
}

/** @extends Rule<Vehicle, VehiclePricingContext, Money> */
final class DefaultVehiclePrice extends Rule
{
    public function evaluate(RuleInput $input): RuleResult
    {
        return RuleResult::applies(
            new Money(30_000_00),
            'The standard vehicle price applies.',
        );
    }
}

/** @extends Rule<Vehicle, VehiclePricingContext, Money> */
final class AustrianVehiclePrice extends Rule
{
    public function priority(): int
    {
        return 50;
    }

    public function evaluate(RuleInput $input): RuleResult
    {
        $context = $input->context(VehiclePricingContext::class);

        if ($context->country !== 'AT') {
            return RuleResult::doesNotApply('The pricing country is not Austria.');
        }

        return RuleResult::applies(
            new Money(34_000_00),
            'The Austrian market price applies.',
        );
    }
}

/** @extends Rule<Vehicle, VehiclePricingContext, Money> */
final class AustrianElectricVehiclePrice extends Rule
{
    public function validity(): ValidityPeriod
    {
        return ValidityPeriod::between(
            new DateTimeImmutable('2026-01-01T00:00:00+01:00'),
            new DateTimeImmutable('2027-01-01T00:00:00+01:00'),
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

        if (! $vehicle->electric) {
            return RuleResult::doesNotApply('The vehicle is not electric.');
        }

        if ($context->country !== 'AT') {
            return RuleResult::doesNotApply('The pricing country is not Austria.');
        }

        return RuleResult::applies(
            new Money(32_500_00),
            'The 2026 Austrian electric-vehicle price applies.',
        );
    }
}

/** @extends Rulebook<Vehicle, VehiclePricingContext, Money> */
final class VehiclePricingRulebook extends Rulebook
{
    /**
     * @return list<class-string<RuleContract<Vehicle, VehiclePricingContext, Money>>>
     */
    protected function rules(): array
    {
        return [
            DefaultVehiclePrice::class,
            AustrianVehiclePrice::class,
            AustrianElectricVehiclePrice::class,
        ];
    }
}
