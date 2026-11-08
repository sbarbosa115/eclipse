<?php

namespace App\Reporting\Application\Tax;

/**
 * A run of whole months, both ends included: the period of a tax report (a month, a bimester, a cuatrimestre…). At most
 * MAX_MONTHS long, so a report stays a working paper and its books' figures stay a handful of queries.
 */
final readonly class MonthRange
{
    public const MAX_MONTHS = 12;

    private const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    private function __construct(public \DateTimeImmutable $first, public \DateTimeImmutable $last)
    {
    }

    /**
     * From YYYY-MM to YYYY-MM; a missing end is the month of $today (the other end when only one is given).
     *
     * @throws \InvalidArgumentException a month that is not YYYY-MM, an end before the start, or more than MAX_MONTHS
     */
    public static function of(?string $from, ?string $to, \DateTimeImmutable $today): self
    {
        $current = $today->format('Y-m');
        $start = self::month($from ?? $to ?? $current, 'from');
        $end = self::month($to ?? $from ?? $current, 'to');
        if ($end < $start) {
            throw new \InvalidArgumentException('"to" is before "from".');
        }
        $range = new self($start, $end->modify('last day of this month'));
        if (\count($range->months()) > self::MAX_MONTHS) {
            throw new \InvalidArgumentException(\sprintf('A report covers at most %d months.', self::MAX_MONTHS));
        }

        return $range;
    }

    /** @return list<string> every month as YYYY-MM, in order */
    public function months(): array
    {
        $months = [];
        for ($m = $this->first; $m <= $this->last; $m = $m->modify('first day of next month')) {
            $months[] = $m->format('Y-m');
        }

        return $months;
    }

    public function from(): string
    {
        return $this->first->format('Y-m');
    }

    public function to(): string
    {
        return $this->last->format('Y-m');
    }

    /** One month of the range as a range of its own. */
    public static function single(string $month): self
    {
        $first = self::month($month, 'month');

        return new self($first, $first->modify('last day of this month'));
    }

    /** "noviembre de 2026", or "septiembre de 2026 a octubre de 2026". */
    public function label(): string
    {
        $name = static fn (\DateTimeImmutable $d) => self::MONTHS[(int) $d->format('n') - 1].' de '.$d->format('Y');

        return $this->from() === $this->to() ? ucfirst($name($this->first)) : ucfirst($name($this->first)).' a '.$name($this->last);
    }

    /** For file names: "2026-11", or "2026-09-a-2026-10". */
    public function slug(): string
    {
        return $this->from() === $this->to() ? $this->from() : $this->from().'-a-'.$this->to();
    }

    private static function month(string $value, string $name): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value.'-01');
        if (1 !== preg_match('/^\d{4}-\d{2}$/', $value) || false === $date || $date->format('Y-m') !== $value) {
            throw new \InvalidArgumentException(\sprintf('"%s" must be a month as YYYY-MM.', $name));
        }

        return $date;
    }
}
