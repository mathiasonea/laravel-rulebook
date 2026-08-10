<?php

namespace MathiasOnea\Rulebook\Snapshots;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;
use MathiasOnea\Rulebook\Evaluations\RuleEvaluationStatus;

final readonly class EvaluationSnapshot implements JsonSerializable
{
    public const SCHEMA_VERSION = 1;

    private ?string $winningRuleKey;

    /** @var list<string> */
    private array $conflictingRuleKeys;

    /**
     * @param  list<RuleEvaluationSnapshot>  $evaluations
     *
     * @internal Evaluation snapshots are created by Evaluation::snapshot().
     */
    public function __construct(
        private DateTimeImmutable $evaluatedAt,
        private array $evaluations,
    ) {
        $evaluationsByKey = [];

        foreach ($evaluations as $evaluation) {
            if (isset($evaluationsByKey[$evaluation->key()])) {
                throw new InvalidArgumentException(sprintf(
                    'Evaluation snapshots must not contain duplicate rule key [%s].',
                    $evaluation->key(),
                ));
            }

            $evaluationsByKey[$evaluation->key()] = $evaluation;
        }

        $applicable = array_values(array_filter(
            $evaluations,
            static fn (RuleEvaluationSnapshot $evaluation): bool => $evaluation->status() === RuleEvaluationStatus::Applicable,
        ));

        if ($applicable === []) {
            $this->winningRuleKey = null;
            $this->conflictingRuleKeys = [];

            return;
        }

        $highestPriority = max(array_map(
            static fn (RuleEvaluationSnapshot $evaluation): int => $evaluation->priority(),
            $applicable,
        ));
        $top = array_values(array_filter(
            $applicable,
            static fn (RuleEvaluationSnapshot $evaluation): bool => $evaluation->priority() === $highestPriority,
        ));

        if (count($top) === 1) {
            $this->winningRuleKey = $top[0]->key();
            $this->conflictingRuleKeys = [];

            return;
        }

        $this->winningRuleKey = null;
        $this->conflictingRuleKeys = array_map(
            static fn (RuleEvaluationSnapshot $evaluation): string => $evaluation->key(),
            $top,
        );
    }

    public function evaluatedAt(): DateTimeImmutable
    {
        return $this->evaluatedAt;
    }

    /**
     * @return list<RuleEvaluationSnapshot>
     */
    public function evaluations(): array
    {
        return $this->evaluations;
    }

    public function winningRuleKey(): ?string
    {
        return $this->winningRuleKey;
    }

    /**
     * @return list<string>
     */
    public function conflictingRuleKeys(): array
    {
        return $this->conflictingRuleKeys;
    }

    public function hasWinner(): bool
    {
        return $this->winningRuleKey !== null;
    }

    public function hasConflict(): bool
    {
        return $this->conflictingRuleKeys !== [];
    }

    /**
     * @return array{
     *     schema_version: int,
     *     evaluated_at: string,
     *     winning_rule_key: string|null,
     *     conflicting_rule_keys: list<string>,
     *     evaluations: list<array<string, int|string|null>>
     * }
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'evaluated_at' => $this->evaluatedAt->format('Y-m-d\TH:i:s.uP'),
            'winning_rule_key' => $this->winningRuleKey,
            'conflicting_rule_keys' => $this->conflictingRuleKeys,
            'evaluations' => array_map(
                static fn (RuleEvaluationSnapshot $evaluation): array => $evaluation->toArray(),
                $this->evaluations,
            ),
        ];
    }

    /**
     * @return array{
     *     schema_version: int,
     *     evaluated_at: string,
     *     winning_rule_key: string|null,
     *     conflicting_rule_keys: list<string>,
     *     evaluations: list<array<string, int|string|null>>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
