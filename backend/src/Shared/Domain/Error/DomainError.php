<?php

namespace App\Shared\Domain\Error;

/**
 * A business rule said no. The error code is machine-readable and part of the API contract; the message is for
 * developers and stays in English.
 *
 * The domain says what kind of "no" it is by the subclass it throws — never an HTTP status: which status each kind
 * becomes is the HTTP layer's business (Shared\UI\Http\ApiExceptionSubscriber). A context names its own errors by
 * extending one of the kinds: `final class CustomerNotFound extends NotFound`.
 */
abstract class DomainError extends \DomainException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * What the client needs to act on the refusal, sent as the response's "detail" (e.g. the unknown product ids of
     * an order). Empty for most errors.
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return [];
    }
}
