<?php

namespace App\Sales\Application\Posting;

use App\Ledger\Application\Posting\EntryLine;
use App\Ledger\Application\Posting\Side;
use App\Shared\Domain\Accounting\PostingConcept;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The movements of one side, summed by where they go (an account or a concept, and the tercero), in the order they
 * first appear. Zero amounts are left out.
 */
final class Movements
{
    /** @var array<string, array{Uuid|PostingConcept, Money, ?Uuid}> */
    private array $byTarget = [];
    private int $separate = 0;

    public function __construct(private readonly Side $side)
    {
    }

    public function add(Uuid|PostingConcept|null $target, Money $amount, ?Uuid $tercero = null): void
    {
        if ($amount->isZero()) {
            return;
        }
        if (null === $target) {
            throw new \LogicException('A tax with an amount has a kind or an account.');
        }
        $key = ($target instanceof Uuid ? $target->toRfc4122() : $target->value).'|'.$tercero?->toRfc4122();
        $this->byTarget[$key] = [$target, isset($this->byTarget[$key]) ? $this->byTarget[$key][1]->plus($amount) : $amount, $tercero];
    }

    public function separate(PostingConcept $concept, Money $amount, Uuid $tercero): void
    {
        if (!$amount->isZero()) {
            $this->byTarget['separate-'.++$this->separate] = [$concept, $amount, $tercero];
        }
    }

    /** @return list<EntryLine> */
    public function lines(): array
    {
        return array_values(array_map(fn (array $m) => $m[0] instanceof Uuid
            ? EntryLine::toAccount($this->side, $m[1], $m[0], $m[2])
            : EntryLine::toConcept($this->side, $m[1], $m[0], $m[2]), $this->byTarget));
    }
}
