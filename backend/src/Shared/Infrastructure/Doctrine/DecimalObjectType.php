<?php

namespace App\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * A DECIMAL column read and written as one of the Shared money value objects, so no float ever holds an amount.
 *
 * @template T of \Stringable
 */
abstract class DecimalObjectType extends Type
{
    abstract protected function scale(): int;

    /**
     * @return T
     */
    abstract protected function fromString(string $value): \Stringable;

    /**
     * @return class-string<T>
     */
    abstract protected function className(): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDecimalTypeDeclarationSQL(['precision' => 18, 'scale' => $this->scale()] + $column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\Stringable
    {
        if (null === $value || $value instanceof \Stringable) {
            return $value;
        }

        return $this->fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        $class = $this->className();
        if (!$value instanceof $class) {
            throw new \InvalidArgumentException(\sprintf('Expected %s, got %s.', $class, get_debug_type($value)));
        }

        return (string) $value;
    }
}
