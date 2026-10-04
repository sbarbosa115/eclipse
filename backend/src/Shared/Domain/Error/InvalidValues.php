<?php

namespace App\Shared\Domain\Error;

/**
 * Several values the person gave break rules only the domain can check, all reported at once, in one round. The same
 * 422 validation_failed as InvalidValue, with one violation per field; messages may carry parameters and are
 * translated like validation messages.
 */
class InvalidValues extends DomainError
{
    /**
     * @param non-empty-list<array{field: string, message: string, parameters?: array<string, string>}> $violations
     */
    public function __construct(private readonly array $violations)
    {
        parent::__construct('validation_failed', 'Validation failed.');
    }

    /**
     * @return non-empty-list<array{field: string, message: string, parameters?: array<string, string>}>
     */
    public function violations(): array
    {
        return $this->violations;
    }
}
