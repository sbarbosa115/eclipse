<?php

namespace App\Shared\Domain\Error;

/**
 * One value the person gave breaks a rule that only the domain can check. It is reported against its field exactly
 * like a form validation error — `validation_failed` with a violation on that field — so the UI shows it next to the
 * input. Shape checks (required, format, length) stay on the Input DTOs.
 */
abstract class InvalidValue extends DomainError
{
    public function __construct(
        private readonly string $field,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct('validation_failed', $message, $previous);
    }

    public function field(): string
    {
        return $this->field;
    }
}
