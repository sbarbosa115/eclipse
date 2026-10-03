<?php

namespace App\Shared\UI\Http;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * An expected API error raised by the HTTP layer itself (a malformed body, a missing parameter). The
 * machine-readable $errorCode is what the UI maps to a message; the English message is for developers.
 */
final class ApiException extends \RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self(404, 'not_found', 'Resource not found.');
    }

    public static function badRequest(string $errorCode, string $message): self
    {
        return new self(400, $errorCode, $message);
    }

    public static function tooManyRequests(string $errorCode, string $message): self
    {
        return new self(429, $errorCode, $message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, mixed> */
    public function getHeaders(): array
    {
        return [];
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
