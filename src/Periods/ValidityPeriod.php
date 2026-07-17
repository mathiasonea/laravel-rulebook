<?php

namespace MathiasOnea\Rulebook\Periods;

use DateTimeImmutable;
use DateTimeInterface;
use MathiasOnea\Rulebook\Exceptions\InvalidValidityPeriod;

final readonly class ValidityPeriod
{
    private function __construct(
        private ?DateTimeImmutable $from,
        private ?DateTimeImmutable $until,
    ) {
        if ($from !== null && $until !== null && $from >= $until) {
            throw new InvalidValidityPeriod($from, $until);
        }
    }

    public static function always(): self
    {
        return new self(null, null);
    }

    public static function from(DateTimeInterface $startsAt): self
    {
        return new self(self::immutable($startsAt), null);
    }

    public static function until(DateTimeInterface $endsAt): self
    {
        return new self(null, self::immutable($endsAt));
    }

    public static function between(DateTimeInterface $from, DateTimeInterface $until): self
    {
        return new self(self::immutable($from), self::immutable($until));
    }

    public function startsAt(): ?DateTimeImmutable
    {
        return $this->from;
    }

    public function endsAt(): ?DateTimeImmutable
    {
        return $this->until;
    }

    public function contains(DateTimeInterface $instant): bool
    {
        $instant = self::immutable($instant);

        return ($this->from === null || $instant >= $this->from)
            && ($this->until === null || $instant < $this->until);
    }

    private static function immutable(DateTimeInterface $dateTime): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($dateTime);
    }
}
