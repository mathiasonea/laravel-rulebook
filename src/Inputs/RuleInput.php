<?php

namespace MathiasOnea\Rulebook\Inputs;

use DateTimeImmutable;
use MathiasOnea\Rulebook\Exceptions\UnexpectedContext;
use MathiasOnea\Rulebook\Exceptions\UnexpectedSubject;

/**
 * @template TSubject of object
 * @template TContext of object|null
 */
final readonly class RuleInput
{
    /**
     * @param  TSubject  $subject
     * @param  TContext  $context
     */
    public function __construct(
        public object $subject,
        public ?object $context,
        public DateTimeImmutable $at,
    ) {}

    /**
     * @template TRequestedSubject of object
     *
     * @param  class-string<TRequestedSubject>  $type
     * @return TRequestedSubject
     */
    public function subject(string $type): object
    {
        if (! $this->subject instanceof $type) {
            throw new UnexpectedSubject($type, $this->subject);
        }

        return $this->subject;
    }

    /**
     * @template TRequestedContext of object
     *
     * @param  class-string<TRequestedContext>  $type
     * @return TRequestedContext
     */
    public function context(string $type): object
    {
        return self::contextOfType($this->context, $type);
    }

    /**
     * @template TRequestedContext of object
     *
     * @param  class-string<TRequestedContext>  $type
     * @return TRequestedContext
     */
    private static function contextOfType(?object $context, string $type): object
    {
        if (! $context instanceof $type) {
            throw new UnexpectedContext($type, $context);
        }

        return $context;
    }
}
