<?php

namespace MathiasOnea\Rulebook\Tests\Fixtures\SubscriptionBilling;

use DateTimeImmutable;
use MathiasOnea\Rulebook\Contracts\Rule as RuleContract;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Periods\ValidityPeriod;
use MathiasOnea\Rulebook\Results\RuleResult;
use MathiasOnea\Rulebook\Rule;
use MathiasOnea\Rulebook\Rulebook;

final readonly class BillingTerms
{
    public function __construct(
        public int $monthlyCents,
        public string $explanation,
    ) {}
}

final readonly class Subscription
{
    public function __construct(
        public string $plan,
        public DateTimeImmutable $startedAt,
    ) {}
}

/** @extends Rule<Subscription, null, BillingTerms> */
final class StandardSubscriptionBilling extends Rule
{
    public function evaluate(RuleInput $input): RuleResult
    {
        return RuleResult::applies(
            new BillingTerms(1_500, 'Current standard monthly rate'),
            'The standard subscription rate applies.',
        );
    }
}

/** @extends Rule<Subscription, null, BillingTerms> */
final class LegacySubscriptionBilling extends Rule
{
    public function validity(): ValidityPeriod
    {
        return ValidityPeriod::until(new DateTimeImmutable('2027-01-01T00:00:00+00:00'));
    }

    public function priority(): int
    {
        return 100;
    }

    public function evaluate(RuleInput $input): RuleResult
    {
        $subscription = $input->subject(Subscription::class);
        $cutoff = new DateTimeImmutable('2025-01-01T00:00:00+00:00');

        if ($subscription->startedAt >= $cutoff) {
            return RuleResult::doesNotApply('The subscription began after the legacy cutoff.');
        }

        return RuleResult::applies(
            new BillingTerms(900, 'Grandfathered rate through 2026'),
            'The subscription retains its grandfathered monthly rate.',
        );
    }
}

/** @extends Rulebook<Subscription, null, BillingTerms> */
final class SubscriptionBillingRulebook extends Rulebook
{
    /**
     * @return list<class-string<RuleContract<Subscription, null, BillingTerms>>>
     */
    protected function rules(): array
    {
        return [
            StandardSubscriptionBilling::class,
            LegacySubscriptionBilling::class,
        ];
    }
}
