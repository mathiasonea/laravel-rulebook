<?php

use MathiasOnea\Rulebook\Tests\Fixtures\SubscriptionBilling\LegacySubscriptionBilling;
use MathiasOnea\Rulebook\Tests\Fixtures\SubscriptionBilling\StandardSubscriptionBilling;
use MathiasOnea\Rulebook\Tests\Fixtures\SubscriptionBilling\Subscription;
use MathiasOnea\Rulebook\Tests\Fixtures\SubscriptionBilling\SubscriptionBillingRulebook;

it('resolves an optional-context rulebook for a grandfathered subscription', function () {
    $decision = app(SubscriptionBillingRulebook::class)->resolveAt(
        subject: new Subscription(
            plan: 'pro',
            startedAt: new DateTimeImmutable('2024-05-01T00:00:00+00:00'),
        ),
        at: new DateTimeImmutable('2026-12-31T23:59:59+00:00'),
    );

    expect($decision->outcome()->monthlyCents)->toBe(900)
        ->and($decision->winningRule())->toBeInstanceOf(LegacySubscriptionBilling::class)
        ->and($decision->shadowedRules()[0])->toBeInstanceOf(StandardSubscriptionBilling::class);
});

it('ends grandfathered billing on the half-open boundary', function () {
    $decision = app(SubscriptionBillingRulebook::class)->resolveAt(
        subject: new Subscription(
            plan: 'pro',
            startedAt: new DateTimeImmutable('2024-05-01T00:00:00+00:00'),
        ),
        at: new DateTimeImmutable('2027-01-01T00:00:00+00:00'),
    );

    expect($decision->outcome()->monthlyCents)->toBe(1_500)
        ->and($decision->winningRule())->toBeInstanceOf(StandardSubscriptionBilling::class)
        ->and($decision->evaluations()[1]->wasEvaluated())->toBeFalse();
});

it('explains why a newer subscription is not grandfathered', function () {
    $decision = app(SubscriptionBillingRulebook::class)->resolveAt(
        subject: new Subscription(
            plan: 'pro',
            startedAt: new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
        ),
        at: new DateTimeImmutable('2026-06-01T00:00:00+00:00'),
    );

    expect($decision->winningRule())->toBeInstanceOf(StandardSubscriptionBilling::class)
        ->and($decision->evaluations()[1]->result()->reason())
        ->toBe('The subscription began after the legacy cutoff.');
});
