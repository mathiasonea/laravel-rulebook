<?php

namespace MathiasOnea\Rulebook\Snapshots;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;
use MathiasOnea\Rulebook\Evaluations\RuleEvaluationStatus;

final readonly class RuleEvaluationSnapshot implements JsonSerializable
{
    /**
     * @param  class-string  $ruleClass
     */
    public function __construct(
        private string $key,
        private string $ruleClass,
        private int $priority,
        private ?DateTimeImmutable $validFrom,
        private ?DateTimeImmutable $validUntil,
        private RuleEvaluationStatus $status,
        private string $reason,
        private ?string $reasonCode,
    ) {
        if (trim($key) === '') {
            throw new InvalidArgumentException('A rule evaluation snapshot key must not be empty.');
        }

        SnapshotValueNormalizer::assertValidString($key, 'evaluation.key');
        SnapshotValueNormalizer::assertValidString($ruleClass, 'evaluation.rule_class');

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A rule evaluation snapshot reason must not be empty.');
        }

        SnapshotValueNormalizer::assertValidString($reason, 'evaluation.reason');

        if ($reasonCode !== null && trim($reasonCode) === '') {
            throw new InvalidArgumentException('A rule evaluation snapshot reason code must not be empty when provided.');
        }

        if ($reasonCode !== null) {
            SnapshotValueNormalizer::assertValidString($reasonCode, 'evaluation.reason_code');
        }

        if ($validFrom !== null && $validUntil !== null && $validFrom >= $validUntil) {
            throw new InvalidArgumentException('A rule evaluation snapshot validity period must end after it starts.');
        }
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return class-string
     */
    public function ruleClass(): string
    {
        return $this->ruleClass;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function validFrom(): ?DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validUntil(): ?DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function status(): RuleEvaluationStatus
    {
        return $this->status;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function reasonCode(): ?string
    {
        return $this->reasonCode;
    }

    /**
     * @return array{
     *     key: string,
     *     rule_class: string,
     *     priority: int,
     *     valid_from: string|null,
     *     valid_until: string|null,
     *     status: string,
     *     reason: string,
     *     reason_code: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'rule_class' => $this->ruleClass,
            'priority' => $this->priority,
            'valid_from' => self::format($this->validFrom),
            'valid_until' => self::format($this->validUntil),
            'status' => $this->status->value,
            'reason' => $this->reason,
            'reason_code' => $this->reasonCode,
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     rule_class: string,
     *     priority: int,
     *     valid_from: string|null,
     *     valid_until: string|null,
     *     status: string,
     *     reason: string,
     *     reason_code: string|null
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function format(?DateTimeImmutable $dateTime): ?string
    {
        return $dateTime?->format('Y-m-d\TH:i:s.uP');
    }
}
