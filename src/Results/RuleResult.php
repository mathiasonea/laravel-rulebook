<?php

namespace MathiasOnea\Rulebook\Results;

use Closure;
use InvalidArgumentException;
use LogicException;

/**
 * @template-covariant TOutcome
 */
final readonly class RuleResult
{
    private function __construct(
        private bool $applicable,
        /** @var Closure(): TOutcome */
        private Closure $outcome,
        private string $reason,
    ) {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A rule result reason must not be empty.');
        }
    }

    /**
     * @template TApplicableOutcome
     *
     * @param  TApplicableOutcome  $outcome
     * @return self<TApplicableOutcome>
     */
    public static function applies(mixed $outcome, string $reason): self
    {
        return new self(true, static fn (): mixed => $outcome, $reason);
    }

    /**
     * @return self<never>
     */
    public static function doesNotApply(string $reason): self
    {
        return new self(
            false,
            static fn (): never => throw new LogicException('An inapplicable rule result has no outcome.'),
            $reason,
        );
    }

    public function isApplicable(): bool
    {
        return $this->applicable;
    }

    public function isInapplicable(): bool
    {
        return ! $this->applicable;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * @return TOutcome
     */
    public function outcome(): mixed
    {
        if (! $this->applicable) {
            throw new LogicException('An inapplicable rule result has no outcome.');
        }

        return ($this->outcome)();
    }
}
