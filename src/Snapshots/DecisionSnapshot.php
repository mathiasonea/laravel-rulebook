<?php

namespace MathiasOnea\Rulebook\Snapshots;

use DateTimeImmutable;
use JsonSerializable;
use LogicException;

final readonly class DecisionSnapshot implements JsonSerializable
{
    public const SCHEMA_VERSION = 1;

    private mixed $outcome;

    private function __construct(
        private EvaluationSnapshot $evaluation,
        mixed $outcome,
    ) {
        if (! $evaluation->hasWinner()) {
            throw new LogicException('A decision snapshot must have a winning rule.');
        }

        $this->outcome = $outcome;
    }

    /**
     * @internal Decision snapshots are created by Decision::snapshot().
     */
    public static function fromOutcome(EvaluationSnapshot $evaluation, mixed $outcome): self
    {
        return new self($evaluation, SnapshotValueNormalizer::normalize($outcome));
    }

    /**
     * @internal Decision snapshots are created by Decision::snapshot().
     */
    public static function fromNormalizedOutcome(
        EvaluationSnapshot $evaluation,
        mixed $outcome,
    ): self {
        return new self($evaluation, SnapshotValueNormalizer::copy($outcome));
    }

    public function evaluatedAt(): DateTimeImmutable
    {
        return $this->evaluation->evaluatedAt();
    }

    public function winningRuleKey(): string
    {
        return $this->evaluation->winningRuleKey()
            ?? throw new LogicException('A decision snapshot must have a winning rule.');
    }

    public function outcome(): mixed
    {
        return $this->outcome;
    }

    public function evaluation(): EvaluationSnapshot
    {
        return $this->evaluation;
    }

    /**
     * @return list<RuleEvaluationSnapshot>
     */
    public function evaluations(): array
    {
        return $this->evaluation->evaluations();
    }

    /**
     * @return array{
     *     schema_version: int,
     *     evaluated_at: string,
     *     winning_rule_key: string,
     *     outcome: mixed,
     *     evaluations: list<array<string, int|string|null>>
     * }
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'evaluated_at' => $this->evaluatedAt()->format('Y-m-d\TH:i:s.uP'),
            'winning_rule_key' => $this->winningRuleKey(),
            'outcome' => $this->outcome,
            'evaluations' => array_map(
                static fn (RuleEvaluationSnapshot $evaluation): array => $evaluation->toArray(),
                $this->evaluations(),
            ),
        ];
    }

    /**
     * @return array{
     *     schema_version: int,
     *     evaluated_at: string,
     *     winning_rule_key: string,
     *     outcome: mixed,
     *     evaluations: list<array<string, int|string|null>>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
