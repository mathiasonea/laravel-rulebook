<?php

use MathiasOnea\Rulebook\Exceptions\UnexpectedContext;
use MathiasOnea\Rulebook\Exceptions\UnexpectedSubject;
use MathiasOnea\Rulebook\Inputs\RuleInput;
use MathiasOnea\Rulebook\Results\RuleResult;
use MathiasOnea\Rulebook\Tests\Fixtures\OtherContext;
use MathiasOnea\Rulebook\Tests\Fixtures\OtherSubject;
use MathiasOnea\Rulebook\Tests\Fixtures\TestContext;
use MathiasOnea\Rulebook\Tests\Fixtures\TestSubject;

it('provides typed subject and context access', function () {
    $subject = new TestSubject('typed');
    $context = new TestContext('api');
    $input = new RuleInput($subject, $context, new DateTimeImmutable);

    expect($input->subject(TestSubject::class))->toBe($subject)
        ->and($input->context(TestContext::class))->toBe($context);
});

it('reports an unexpected subject with useful runtime context', function () {
    $subject = new TestSubject;
    $input = new RuleInput($subject, null, new DateTimeImmutable);

    try {
        $input->subject(OtherSubject::class);
    } catch (UnexpectedSubject $exception) {
        expect($exception->expected)->toBe(OtherSubject::class)
            ->and($exception->actual)->toBe($subject)
            ->and($exception->getMessage())->toContain(TestSubject::class);

        return;
    }

    test()->fail('UnexpectedSubject was not thrown.');
});

it('reports missing and mistyped optional contexts', function (?object $context, string $actualType) {
    $input = new RuleInput(new TestSubject, $context, new DateTimeImmutable);

    try {
        $input->context(TestContext::class);
    } catch (UnexpectedContext $exception) {
        expect($exception->expected)->toBe(TestContext::class)
            ->and($exception->actual)->toBe($context)
            ->and($exception->getMessage())->toContain($actualType);

        return;
    }

    test()->fail('UnexpectedContext was not thrown.');
})->with([
    'missing' => [null, 'null'],
    'wrong type' => [new OtherContext, OtherContext::class],
]);

it('requires a non-empty reason for every result', function (string $reason) {
    expect(fn () => RuleResult::applies('outcome', $reason))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => RuleResult::doesNotApply($reason))
        ->toThrow(InvalidArgumentException::class);
})->with(['', '   ', "\n\t"]);

it('requires a non-empty reason code when one is provided', function (string $reasonCode) {
    expect(fn () => RuleResult::applies('outcome', 'A useful reason.', $reasonCode))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => RuleResult::doesNotApply('A useful reason.', $reasonCode))
        ->toThrow(InvalidArgumentException::class);
})->with(['', '   ', "\n\t"]);

it('retains applicability, outcome, and explanation', function () {
    $result = RuleResult::applies('price', 'The contract price applies.', 'contract_price');

    expect($result->isApplicable())->toBeTrue()
        ->and($result->isInapplicable())->toBeFalse()
        ->and($result->outcome())->toBe('price')
        ->and($result->reason())->toBe('The contract price applies.')
        ->and($result->reasonCode())->toBe('contract_price');
});

it('distinguishes a nullable applicable outcome from inapplicability', function () {
    $applicable = RuleResult::applies(null, 'No charge is the selected outcome.');
    $inapplicable = RuleResult::doesNotApply('The waiver does not apply.');

    expect($applicable->isApplicable())->toBeTrue()
        ->and($applicable->outcome())->toBeNull()
        ->and($inapplicable->isInapplicable())->toBeTrue()
        ->and(fn () => $inapplicable->outcome())->toThrow(LogicException::class);
});
