<?php

namespace App\Company\Domain\Model;

use App\Company\Domain\Error\InvalidResolution;
use App\Company\Domain\Error\ResolutionExhausted;
use App\Company\Domain\Error\ResolutionInactive;
use App\Shared\Domain\Model\CompanyOwned;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * The DIAN authorisation to invoice (§4.1): a prefix, a range desde–hasta and a validity period. One per company in
 * stage 1 (§9 Q12). Its next number is taken under a row lock when a sales invoice is emitted, so two emissions never
 * share a number and a number is never reused.
 */
#[ORM\Entity]
#[ORM\Table(name: 'invoicing_resolution')]
#[ORM\Index(name: 'invoicing_resolution_company', columns: ['company_id'])]
class InvoicingResolution implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column]
    private int $nextNumber;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 40)]
        private string $resolutionNumber,
        #[ORM\Column(length: 10)]
        private string $prefix,
        #[ORM\Column]
        private int $rangeFrom,
        #[ORM\Column]
        private int $rangeTo,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $validFrom,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $validTo,
        #[ORM\Column(length: 12, enumType: InvoicingMode::class)]
        private InvoicingMode $mode,
    ) {
        $this->id = Uuid::v7();
        $this->nextNumber = $rangeFrom;
    }

    /**
     * A resolution as the owner types it, checked against §4.1's rules.
     *
     * @param bool $manualConfirmed the owner confirmed the DIAN permission to invoice manually
     *
     * @throws InvalidResolution
     */
    public static function define(Uuid $companyId, string $resolutionNumber, string $prefix, int $rangeFrom, int $rangeTo, \DateTimeImmutable $validFrom, \DateTimeImmutable $validTo, InvoicingMode $mode, bool $manualConfirmed): self
    {
        $prefix = strtoupper($prefix);
        self::check($prefix, $rangeFrom, $rangeTo, $validFrom, $validTo, $mode, $manualConfirmed, []);

        return new self($companyId, trim($resolutionNumber), $prefix, $rangeFrom, $rangeTo, $validFrom->setTime(0, 0), $validTo->setTime(0, 0), $mode);
    }

    /**
     * Edits the resolution. Once an invoice was numbered from it, desde and the prefix are fixed (they are printed on
     * emitted invoices) and hasta cannot drop below the last number used. Moving desde before that moves the next
     * number with it; afterwards the consecutive is never rewound.
     *
     * @throws InvalidResolution
     */
    public function revise(string $resolutionNumber, string $prefix, int $rangeFrom, int $rangeTo, \DateTimeImmutable $validFrom, \DateTimeImmutable $validTo, InvoicingMode $mode, bool $manualConfirmed): void
    {
        $prefix = strtoupper($prefix);
        $locked = [];
        if ($this->hasIssuedNumbers()) {
            if ($rangeFrom !== $this->rangeFrom) {
                $locked[] = ['field' => 'range_from', 'message' => 'Desde cannot change once invoices were numbered from this resolution.'];
            }
            if ($prefix !== $this->prefix) {
                $locked[] = ['field' => 'prefix', 'message' => 'The prefix cannot change once invoices were numbered from this resolution.'];
            }
            if ($rangeTo < $this->nextNumber - 1) {
                $locked[] = ['field' => 'range_to', 'message' => 'Hasta cannot be below the last number already used ({{ number }}).', 'parameters' => ['{{ number }}' => (string) ($this->nextNumber - 1)]];
            }
        }
        self::check($prefix, $rangeFrom, $rangeTo, $validFrom, $validTo, $mode, $manualConfirmed, $locked);

        if (!$this->hasIssuedNumbers()) {
            $this->nextNumber = $rangeFrom;
        }
        $this->resolutionNumber = trim($resolutionNumber);
        $this->prefix = $prefix;
        $this->rangeFrom = $rangeFrom;
        $this->rangeTo = $rangeTo;
        $this->validFrom = $validFrom->setTime(0, 0);
        $this->validTo = $validTo->setTime(0, 0);
        $this->mode = $mode;
    }

    /**
     * @param list<array{field: string, message: string, parameters?: array<string, string>}> $violations already found
     */
    private static function check(string $prefix, int $rangeFrom, int $rangeTo, \DateTimeImmutable $validFrom, \DateTimeImmutable $validTo, InvoicingMode $mode, bool $manualConfirmed, array $violations): void
    {
        $add = static function (string $field, string $message) use (&$violations): void {
            foreach ($violations as $v) {
                if ($v['field'] === $field) {
                    return;
                }
            }
            $violations[] = ['field' => $field, 'message' => $message];
        };
        if (1 !== preg_match('/^[A-Z0-9]{0,10}$/', $prefix)) {
            $add('prefix', 'The prefix has up to ten letters and digits.');
        }
        if ($rangeFrom < 1) {
            $add('range_from', 'Numbers start at 1.');
        }
        if ($rangeTo < $rangeFrom) {
            $add('range_to', 'Hasta cannot be lower than desde.');
        }
        if ($validTo->format('Y-m-d') < $validFrom->format('Y-m-d')) {
            $add('valid_to', 'The end date cannot be before the start date.');
        }
        if (InvoicingMode::Manual === $mode && !$manualConfirmed) {
            $add('mode', 'Manual invoicing needs the owner to confirm the DIAN permission first.');
        }
        if ([] !== $violations) {
            throw new InvalidResolution($violations);
        }
    }

    /** Numbers have been taken from it: invoices carry them. */
    public function hasIssuedNumbers(): bool
    {
        return $this->nextNumber > $this->rangeFrom;
    }

    public function numbersLeft(): int
    {
        return max(0, $this->rangeTo - $this->nextNumber + 1);
    }

    /** Whole days after today until the last valid day (0 on that day, and once it is over). */
    public function daysLeft(\DateTimeImmutable $today): int
    {
        $diff = $today->setTime(0, 0)->diff($this->validTo->setTime(0, 0));

        return $diff->invert ? 0 : (int) $diff->days;
    }

    public function status(\DateTimeImmutable $today): ResolutionStatus
    {
        $day = $today->format('Y-m-d');

        return match (true) {
            $day < $this->validFrom->format('Y-m-d') => ResolutionStatus::NotYetValid,
            $day > $this->validTo->format('Y-m-d') => ResolutionStatus::Expired,
            $this->nextNumber > $this->rangeTo => ResolutionStatus::Exhausted,
            default => ResolutionStatus::Active,
        };
    }

    /** Active, but fewer numbers or days left than the company asked to be warned at. */
    public function isRunningOut(\DateTimeImmutable $today, int $warnNumbers, int $warnDays): bool
    {
        return ResolutionStatus::Active === $this->status($today)
            && ($this->numbersLeft() < $warnNumbers || $this->daysLeft($today) < $warnDays);
    }

    /**
     * Gives the next authorised number for an invoice dated $on and moves on. The caller holds the row lock.
     *
     * @throws ResolutionInactive|ResolutionExhausted
     */
    public function take(\DateTimeImmutable $on): int
    {
        match ($this->status($on)) {
            ResolutionStatus::NotYetValid, ResolutionStatus::Expired => throw new ResolutionInactive(),
            ResolutionStatus::Exhausted => throw new ResolutionExhausted(),
            ResolutionStatus::Active => null,
        };

        return $this->nextNumber++;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function resolutionNumber(): string
    {
        return $this->resolutionNumber;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function rangeFrom(): int
    {
        return $this->rangeFrom;
    }

    public function rangeTo(): int
    {
        return $this->rangeTo;
    }

    public function validFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): \DateTimeImmutable
    {
        return $this->validTo;
    }

    public function mode(): InvoicingMode
    {
        return $this->mode;
    }

    public function nextNumber(): int
    {
        return $this->nextNumber;
    }
}
