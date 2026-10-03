<?php

namespace App\Shared\UI\Http;

use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Rendered as 422 {"error": "validation_failed", "violations": [{"field", "message"}]}.
 *
 * Messages are written in English and translated on the way out, in the "validators" domain like the
 * validator's own (translations/validators.es.yaml). A value that varies stays out of the text — "Upload at
 * most %max% photos." with ['%max%' => 10] — so one translation serves every value.
 */
final class ApiValidationException extends \RuntimeException
{
    /**
     * @param list<array{field: string, message: string, parameters?: array<string, string|int>}> $violations
     */
    public function __construct(private readonly array $violations)
    {
        parent::__construct('Validation failed.');
    }

    /**
     * @param array<string, string|int> $parameters
     */
    public static function single(string $field, string $message, array $parameters = []): self
    {
        return new self([['field' => $field, 'message' => $message, 'parameters' => $parameters]]);
    }

    public static function fromViolations(ConstraintViolationListInterface $list, ?string $field = null): self
    {
        $violations = [];
        foreach ($list as $violation) {
            $violations[] = ['field' => $field ?? self::snakeCase($violation->getPropertyPath()), 'message' => (string) $violation->getMessage()];
        }

        return new self($violations);
    }

    /**
     * The violations in English, with their values filled in.
     *
     * @return list<array{field: string, message: string}>
     */
    public function getViolations(): array
    {
        return array_map(
            static fn (array $v) => ['field' => $v['field'], 'message' => strtr($v['message'], array_map('strval', $v['parameters'] ?? []))],
            $this->violations,
        );
    }

    /**
     * The violations in the request's language; a message nobody translated stays in English.
     *
     * @return list<array{field: string, message: string}>
     */
    public function getTranslatedViolations(TranslatorInterface $translator): array
    {
        return array_map(
            static fn (array $v) => ['field' => $v['field'], 'message' => $translator->trans($v['message'], $v['parameters'] ?? [], 'validators')],
            $this->violations,
        );
    }

    /**
     * The API is snake_case: "carouselSlides[0].image" is reported as "carousel_slides[0].image".
     */
    private static function snakeCase(string $path): string
    {
        return strtolower((string) preg_replace('/(?<=[a-z0-9])[A-Z]/', '_$0', $path));
    }
}
