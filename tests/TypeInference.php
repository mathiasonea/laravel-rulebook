<?php

namespace MathiasOnea\Rulebook\Tests;

use DateTimeImmutable;
use MathiasOnea\Rulebook\Decisions\Decision;
use MathiasOnea\Rulebook\Evaluations\Evaluation;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Tests\Fixtures\VehiclePricing\Money;
use MathiasOnea\Rulebook\Tests\Fixtures\VehiclePricing\Vehicle;
use MathiasOnea\Rulebook\Tests\Fixtures\VehiclePricing\VehiclePricingContext;
use MathiasOnea\Rulebook\Tests\Fixtures\VehiclePricing\VehiclePricingRulebook;

use function PHPStan\Testing\assertType;

function assertRulebookTypes(
    VehiclePricingRulebook $rulebook,
    Vehicle $vehicle,
    VehiclePricingContext $context,
): void {
    assertType(
        Decision::class.'<'.Vehicle::class.', '.VehiclePricingContext::class.', '.Money::class.'>',
        $rulebook->resolveNow($vehicle, $context),
    );
    assertType(Money::class, $rulebook->resolveNow($vehicle, $context)->outcome());

    assertType(
        Evaluation::class.'<'.Vehicle::class.', '.VehiclePricingContext::class.', '.Money::class.'>',
        $rulebook->evaluateAt($vehicle, new DateTimeImmutable, $context),
    );
    assertType(
        RuleInput::class.'<'.Vehicle::class.', '.VehiclePricingContext::class.'>',
        $rulebook->evaluateNow($vehicle, $context)->input(),
    );
    assertType(Vehicle::class, $rulebook->evaluateNow($vehicle, $context)->input()->subject(Vehicle::class));
    assertType(
        VehiclePricingContext::class,
        $rulebook->evaluateNow($vehicle, $context)->input()->context(VehiclePricingContext::class),
    );
}
