<?php

namespace App\Shared\UI\Http;

use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Turns request data into a validated input object.
 *
 * Types are enforced strictly for JSON, so e.g. a money amount sent as a
 * number instead of a decimal string is rejected with a 422.
 *
 * PATCH is implemented by merging the request over the entity's current
 * values (Input::fromEntity) and validating the full result.
 */
final class InputMapper
{
    public function __construct(
        private readonly DenormalizerInterface $denormalizer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @template T of object
     *
     * @param array<string, mixed> $data
     * @param class-string<T>      $class
     *
     * @return T
     */
    public function map(array $data, string $class): object
    {
        try {
            $input = $this->denormalizer->denormalize($data, $class, null, [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);
        } catch (PartialDenormalizationException $e) {
            throw new ApiValidationException(array_values(array_map(static fn (NotNormalizableValueException $error) => ['field' => (string) $error->getPath(), 'message' => 'This value should be of type %type%.', 'parameters' => ['%type%' => implode('|', $error->getExpectedTypes() ?? ['unknown'])]], $e->getNotNormalizableValueErrors())));
        }

        $violations = $this->validator->validate($input);
        if (\count($violations) > 0) {
            throw ApiValidationException::fromViolations($violations);
        }

        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(Request $request): array
    {
        if ('' === $request->getContent()) {
            return [];
        }

        try {
            return $request->toArray();
        } catch (JsonException) {
            throw ApiException::badRequest('invalid_json', 'Request body must be a JSON object.');
        }
    }

    /**
     * Multipart form fields. Empty strings become null so optional fields can be left blank.
     *
     * @return array<string, mixed>
     */
    public function form(Request $request): array
    {
        return array_map(static fn (mixed $value) => '' === $value ? null : $value, $request->request->all());
    }
}
