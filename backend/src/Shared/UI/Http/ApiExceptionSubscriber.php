<?php

namespace App\Shared\UI\Http;

use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\ExternalServiceFailed;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\Error\InvalidValues;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Error\Refused;
use App\Shared\Domain\Error\Rejected;
use App\Shared\Domain\Error\ServiceUnavailable;
use App\Shared\Domain\Error\TooLarge;
use App\Shared\Domain\Error\TooManyAttempts;
use App\Shared\Domain\Error\Unauthorized;
use App\Shared\Domain\Error\UnsupportedMedia;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Every /api error is JSON: {"error": "<code>", "message": "...", "detail"?: {...}, "violations"?: [...]}.
 * "message" is what the PRD's clients read; "error" is the stable code; "detail" carries what the client needs to act
 * (DomainError::details()).
 *
 * This is where a domain's "no" becomes an HTTP status: Rejected 400, Unauthorized 401, NotAllowed 403, NotFound 404,
 * Conflict 409, TooLarge 413, UnsupportedMedia 415, Refused 422, TooManyAttempts 429, ExternalServiceFailed 502, ServiceUnavailable 503, and an
 * InvalidValue a 422 with a violation on its field — the same answer an Input DTO's constraint gives.
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthorized',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        413 => 'payload_too_large',
        415 => 'unsupported_media_type',
        429 => 'too_many_requests',
    ];

    public function __construct(
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 0]];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $e = $event->getThrowable();

        $response = match (true) {
            $e instanceof ApiValidationException => self::error(422, 'validation_failed', 'Validation error', ['violations' => $e->getTranslatedViolations($this->translator)]),
            $e instanceof ApiException => self::error($e->getStatusCode(), $e->getErrorCode(), $e->getMessage()),
            $e instanceof DomainError => $this->domainError($e),
            $e instanceof HttpExceptionInterface => self::error(
                $e->getStatusCode(),
                self::CODES[$e->getStatusCode()] ?? 'http_error',
                // The framework's own text can quote the request ("No route found for GET …"): never echoed back.
                Response::$statusTexts[$e->getStatusCode()] ?? 'Error',
                headers: $e->getHeaders(),
            ),
            $this->debug => null, // keep Symfony's detailed error page in dev
            default => self::error(500, 'internal_error', 'Internal server error.'),
        };

        if (null !== $response) {
            $event->setResponse($response);
        }
    }

    private function domainError(DomainError $e): JsonResponse
    {
        if ($e instanceof InvalidValues) {
            $violations = array_map(
                fn (array $v) => ['field' => $v['field'], 'message' => $this->translator->trans($v['message'], $v['parameters'] ?? [], 'validators')],
                $e->violations(),
            );

            return self::error(422, 'validation_failed', 'Validation error', ['violations' => $violations]);
        }
        if ($e instanceof InvalidValue) {
            $violation = ['field' => $e->field(), 'message' => $this->translator->trans($e->getMessage(), [], 'validators')];

            return self::error(422, 'validation_failed', 'Validation error', ['violations' => [$violation]]);
        }

        $status = match (true) {
            $e instanceof Rejected => 400,
            $e instanceof Unauthorized => 401,
            $e instanceof NotAllowed => 403,
            $e instanceof NotFound => 404,
            $e instanceof Conflict => 409,
            $e instanceof TooLarge => 413,
            $e instanceof UnsupportedMedia => 415,
            $e instanceof Refused => 422,
            $e instanceof TooManyAttempts => 429,
            $e instanceof ExternalServiceFailed => 502,
            $e instanceof ServiceUnavailable => 503,
            default => throw new \LogicException(\sprintf('%s extends no kind of DomainError the HTTP layer knows.', $e::class), previous: $e),
        };

        $details = $e->details();

        return self::error($status, $e->errorCode(), $e->getMessage(), [] === $details ? [] : ['detail' => $details]);
    }

    /**
     * @param array<string, mixed>  $extra
     * @param array<string, string> $headers
     */
    private static function error(int $status, string $code, string $message, array $extra = [], array $headers = []): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message] + $extra, $status, $headers);
    }
}
