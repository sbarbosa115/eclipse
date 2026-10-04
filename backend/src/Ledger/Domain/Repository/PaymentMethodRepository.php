<?php

namespace App\Ledger\Domain\Repository;

use App\Ledger\Domain\Error\PaymentMethodNotFound;
use App\Ledger\Domain\Model\PaymentMethod;
use Symfony\Component\Uid\Uuid;

interface PaymentMethodRepository
{
    /** @throws PaymentMethodNotFound another company's id is "not found" too */
    public function get(Uuid $companyId, Uuid $id): PaymentMethod;

    public function add(PaymentMethod $method): void;

    public function remove(PaymentMethod $method): void;

    /** Whether the company has a method with this name (ignoring case and the method `$except`). */
    public function nameTaken(Uuid $companyId, string $name, ?Uuid $except = null): bool;
}
