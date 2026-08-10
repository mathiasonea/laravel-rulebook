<?php

namespace MathiasOnea\Rulebook\Tests;

use DateTimeImmutable;
use MathiasOnea\Rulebook\Decisions\Decision;
use MathiasOnea\Rulebook\Evaluations\Evaluation;
use MathiasOnea\Rulebook\Evaluations\RuleEvaluationStatus;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Snapshots\DecisionSnapshot;
use MathiasOnea\Rulebook\Snapshots\EvaluationSnapshot;
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
    assertType('string', $rulebook->resolveNow($vehicle, $context)->winningRuleKey());
    assertType(
        DecisionSnapshot::class,
        $rulebook->resolveNow($vehicle, $context)->snapshot(
            static fn (Money $money): array => ['cents' => $money->cents],
        ),
    );

    assertType(
        Evaluation::class.'<'.Vehicle::class.', '.VehiclePricingContext::class.', '.Money::class.'>',
        $rulebook->evaluateAt($vehicle, new DateTimeImmutable, $context),
    );
    assertType(
        RuleInput::class.'<'.Vehicle::class.', '.VehiclePricingContext::class.'>',
        $rulebook->evaluateNow($vehicle, $context)->input(),
    );
    assertType(EvaluationSnapshot::class, $rulebook->evaluateNow($vehicle, $context)->snapshot());
    assertType(
        RuleEvaluationStatus::class,
        $rulebook->evaluateNow($vehicle, $context)->evaluations()[0]->status(),
    );
    assertType(Vehicle::class, $rulebook->evaluateNow($vehicle, $context)->input()->subject(Vehicle::class));
    assertType(
        VehiclePricingContext::class,
        $rulebook->evaluateNow($vehicle, $context)->input()->context(VehiclePricingContext::class),
    );
}
