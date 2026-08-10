<?php

use MathiasOnea\Rulebook\Evaluations\RuleEvaluationStatus;
use MathiasOnea\Rulebook\Exceptions\UnportableSnapshotValue;
use MathiasOnea\Rulebook\Snapshots\DecisionSnapshot;
use MathiasOnea\Rulebook\Tests\Fixtures\AlwaysApplicableRule;
use MathiasOnea\Rulebook\Tests\Fixtures\EqualPriorityRule;
use MathiasOnea\Rulebook\Tests\Fixtures\HigherPriorityRule;
use MathiasOnea\Rulebook\Tests\Fixtures\InapplicableRule;
use MathiasOnea\Rulebook\Tests\Fixtures\InvalidUtf8MetadataRule;
use MathiasOnea\Rulebook\Tests\Fixtures\NullableOutcomeRule;
use MathiasOnea\Rulebook\Tests\Fixtures\TestSubject;
use MathiasOnea\Rulebook\Tests\Fixtures\WindowedRule;

it('creates a portable snapshot of a resolved decision', function () {
    $decision = rulebookWith([
        AlwaysApplicableRule::class,
        InapplicableRule::class,
        WindowedRule::class,
    ])->resolveAt(
        new TestSubject,
        new DateTimeImmutable('2027-01-01T00:00:00+00:00'),
    );

    $snapshot = $decision->snapshot();
    $array = $snapshot->toArray();

    expect($snapshot->winningRuleKey())->toBe(AlwaysApplicableRule::class)
        ->and($snapshot->outcome())->toBe('default')
        ->and($snapshot->evaluations())->toHaveCount(3)
        ->and($snapshot->evaluations()[0]->status())->toBe(RuleEvaluationStatus::Applicable)
        ->and($snapshot->evaluations()[1]->status())->toBe(RuleEvaluationStatus::DoesNotApply)
        ->and($snapshot->evaluations()[1]->reasonCode())->toBe('subject_ineligible')
        ->and($snapshot->evaluations()[2]->status())->toBe(RuleEvaluationStatus::OutsideValidity)
        ->and(array_keys($array))->toBe([
            'schema_version',
            'evaluated_at',
            'winning_rule_key',
            'outcome',
            'evaluations',
        ])
        ->and($array['schema_version'])->toBe(1)
        ->and($array['evaluated_at'])->toBe('2027-01-01T00:00:00.000000+00:00')
        ->and($array['winning_rule_key'])->toBe(AlwaysApplicableRule::class)
        ->and($array['evaluations'][2]['valid_until'])->toBe('2027-01-01T00:00:00.000000+00:00')
        ->and(json_decode(json_encode($snapshot, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR))
        ->toBe($array);
});

it('snapshots unresolved conflicts without requiring a decision', function () {
    $snapshot = rulebookWith([
        HigherPriorityRule::class,
        EqualPriorityRule::class,
    ])->evaluateNow(new TestSubject)->snapshot();

    $array = $snapshot->toArray();

    expect($snapshot->hasWinner())->toBeFalse()
        ->and($snapshot->hasConflict())->toBeTrue()
        ->and($snapshot->winningRuleKey())->toBeNull()
        ->and($snapshot->conflictingRuleKeys())->toBe([
            HigherPriorityRule::class,
            EqualPriorityRule::class,
        ])
        ->and($array['schema_version'])->toBe(1)
        ->and($array['winning_rule_key'])->toBeNull()
        ->and($array['conflicting_rule_keys'])->toBe([
            HigherPriorityRule::class,
            EqualPriorityRule::class,
        ]);
});

it('snapshots a missing match without inventing a conflict', function () {
    $snapshot = rulebookWith([InapplicableRule::class])
        ->evaluateAt(new TestSubject, new DateTimeImmutable('2026-08-10T12:00:00+02:00'))
        ->snapshot();

    expect($snapshot->hasWinner())->toBeFalse()
        ->and($snapshot->hasConflict())->toBeFalse()
        ->and($snapshot->winningRuleKey())->toBeNull()
        ->and($snapshot->conflictingRuleKeys())->toBeEmpty()
        ->and($snapshot->toArray())->toMatchArray([
            'schema_version' => 1,
            'evaluated_at' => '2026-08-10T12:00:00.000000+02:00',
            'winning_rule_key' => null,
            'conflicting_rule_keys' => [],
        ]);
});

it('eagerly copies json serializable outcomes', function () {
    $outcome = new class implements JsonSerializable
    {
        public string $value = 'original';

        public function jsonSerialize(): array
        {
            return ['value' => $this->value];
        }
    };
    $evaluation = rulebookWith([AlwaysApplicableRule::class])
        ->evaluateNow(new TestSubject)
        ->snapshot();

    $snapshot = DecisionSnapshot::fromOutcome($evaluation, $outcome);
    $outcome->value = 'changed';

    expect($snapshot->outcome())->toBe(['value' => 'original']);
});

it('rejects values that JSON cannot transport safely', function (mixed $outcome) {
    $evaluation = rulebookWith([AlwaysApplicableRule::class])
        ->evaluateNow(new TestSubject)
        ->snapshot();

    expect(fn () => DecisionSnapshot::fromOutcome($evaluation, $outcome))
        ->toThrow(UnportableSnapshotValue::class);
})->with([
    'unsupported object' => [new stdClass],
    'non-finite number' => [NAN],
    'invalid UTF-8 string' => ["invalid\xFF"],
    'invalid UTF-8 array key' => [["invalid\xFF" => 'value']],
]);

it('rejects resource outcomes', function () {
    $evaluation = rulebookWith([AlwaysApplicableRule::class])
        ->evaluateNow(new TestSubject)
        ->snapshot();
    $resource = fopen('php://memory', 'r');

    try {
        expect(fn () => DecisionSnapshot::fromOutcome($evaluation, $resource))
            ->toThrow(UnportableSnapshotValue::class);
    } finally {
        if (is_resource($resource)) {
            fclose($resource);
        }
    }
});

it('preserves a nullable outcome in a portable snapshot', function () {
    $snapshot = rulebookWith([NullableOutcomeRule::class])
        ->resolveNow(new TestSubject)
        ->snapshot();

    expect($snapshot->outcome())->toBeNull()
        ->and($snapshot->toArray()['outcome'])->toBeNull();
});

it('rejects invalid UTF-8 evaluation metadata when creating a snapshot', function (
    string $field,
    string $path,
) {
    app()->instance(InvalidUtf8MetadataRule::class, new InvalidUtf8MetadataRule($field));

    $decision = rulebookWith([InvalidUtf8MetadataRule::class])->resolveNow(new TestSubject);

    expect(fn () => $decision->snapshot())
        ->toThrow(UnportableSnapshotValue::class, $path);
})->with([
    'rule key' => ['key', 'evaluation.key'],
    'reason' => ['reason', 'evaluation.reason'],
    'reason code' => ['reasonCode', 'evaluation.reason_code'],
]);
