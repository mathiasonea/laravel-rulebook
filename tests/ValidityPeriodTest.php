<?php

use MathiasOnea\Rulebook\Exceptions\InvalidValidityPeriod;
use MathiasOnea\Rulebook\Periods\ValidityPeriod;

it('is always valid when no boundaries exist', function () {
    $period = ValidityPeriod::always();

    expect($period)
        ->startsAt()->toBeNull()
        ->endsAt()->toBeNull()
        ->and($period->contains(new DateTimeImmutable('1900-01-01')))->toBeTrue()
        ->and($period->contains(new DateTimeImmutable('2999-12-31')))->toBeTrue();
});

it('supports an inclusive open-ended start', function () {
    $start = new DateTimeImmutable('2026-05-10T10:30:00.123456+02:00');
    $period = ValidityPeriod::from($start);

    expect($period->contains($start))->toBeTrue()
        ->and($period->contains($start->modify('-1 microsecond')))->toBeFalse()
        ->and($period->contains($start->modify('+20 years')))->toBeTrue();
});

it('supports an exclusive open-start end', function () {
    $end = new DateTimeImmutable('2026-05-10T10:30:00.123456+02:00');
    $period = ValidityPeriod::until($end);

    expect($period->contains($end->modify('-1 microsecond')))->toBeTrue()
        ->and($period->contains($end))->toBeFalse()
        ->and($period->contains($end->modify('+1 microsecond')))->toBeFalse();
});

it('uses half-open boundaries for finite periods', function () {
    $start = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $end = new DateTimeImmutable('2027-01-01T00:00:00+00:00');
    $period = ValidityPeriod::between($start, $end);

    expect($period->contains($start))->toBeTrue()
        ->and($period->contains($end->modify('-1 microsecond')))->toBeTrue()
        ->and($period->contains($end))->toBeFalse();
});

it('lets consecutive periods meet without a gap or overlap', function () {
    $boundary = new DateTimeImmutable('2027-01-01T00:00:00+00:00');
    $first = ValidityPeriod::between(
        new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        $boundary,
    );
    $second = ValidityPeriod::between(
        $boundary,
        new DateTimeImmutable('2028-01-01T00:00:00+00:00'),
    );

    expect($first->contains($boundary))->toBeFalse()
        ->and($second->contains($boundary))->toBeTrue();
});

it('compares absolute instants across timezones', function () {
    $period = ValidityPeriod::between(
        new DateTimeImmutable('2026-01-01T00:00:00+01:00'),
        new DateTimeImmutable('2026-01-01T01:00:00+01:00'),
    );

    expect($period->contains(new DateTimeImmutable('2025-12-31T23:00:00+00:00')))->toBeTrue()
        ->and($period->contains(new DateTimeImmutable('2026-01-01T00:00:00+00:00')))->toBeFalse();
});

it('copies mutable date inputs into immutable values', function () {
    $start = new DateTime('2026-01-01T00:00:00+00:00');
    $period = ValidityPeriod::from($start);

    $start->modify('+1 year');

    expect($period->startsAt())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($period->startsAt()?->format('Y-m-d'))->toBe('2026-01-01');
});

it('rejects empty and reversed periods', function (string $from, string $until) {
    expect(fn () => ValidityPeriod::between(
        new DateTimeImmutable($from),
        new DateTimeImmutable($until),
    ))->toThrow(InvalidValidityPeriod::class);
})->with([
    'empty' => ['2026-01-01T00:00:00+00:00', '2026-01-01T00:00:00+00:00'],
    'reversed' => ['2026-01-02T00:00:00+00:00', '2026-01-01T00:00:00+00:00'],
    'timezone-equivalent empty' => ['2026-01-01T01:00:00+01:00', '2026-01-01T00:00:00+00:00'],
]);
