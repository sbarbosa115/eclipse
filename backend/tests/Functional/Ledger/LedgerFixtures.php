<?php

namespace App\Tests\Functional\Ledger;

use App\Access\Application\Port\PasswordHasher;
use App\Access\Domain\Model\User;
use App\Ledger\Application\Posting\EntryDraft;
use App\Ledger\Application\Posting\EntryLine;
use App\Ledger\Application\Posting\JournalPoster;
use App\Ledger\Application\Posting\Side;
use App\Ledger\Application\Query\LedgerCatalog;
use App\Ledger\Domain\Model\JournalEntry;
use App\Party\Domain\Model\Tercero;
use App\Shared\Domain\Fiscal\IdentificationType;
use App\Shared\Domain\Fiscal\PersonType;
use App\Shared\Domain\Money\Money;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\SignsUp;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * What the ledger's tests share: a signed-up company, its accounts by code, the real poster (documents call it inside
 * their own transaction; here the test flushes), terceros, and users of each role.
 *
 * @mixin ApiTestCase
 */
trait LedgerFixtures
{
    use SignsUp;

    protected Uuid $company;
    protected Uuid $owner;

    protected function startCompany(string $email = 'ana@acme.co', string $nit = '900123456', string $name = 'Acme S.A.S.'): Uuid
    {
        $session = $this->signUp($email, $nit, $name);
        $this->company = Uuid::fromString($session['company_id']);
        $this->owner = Uuid::fromString($session['user_id']);

        return $this->company;
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function db(): Connection
    {
        return $this->em()->getConnection();
    }

    protected function account(string $code, ?Uuid $company = null): Uuid
    {
        return static::getContainer()->get(LedgerCatalog::class)->accountIdByCode($company ?? $this->company, $code)
            ?? throw new \LogicException("The chart has no $code.");
    }

    protected function poster(): JournalPoster
    {
        return static::getContainer()->get(JournalPoster::class);
    }

    /**
     * Posts as a document would, and commits what it wrote (the command bus does that for documents).
     *
     * @param list<EntryLine> $lines
     */
    protected function post(array $lines, string $date = '2026-10-01', string $number = 'FE-1', string $type = 'sales_invoice', ?Uuid $company = null): Uuid
    {
        $draft = new EntryDraft($company ?? $this->company, new \DateTimeImmutable($date), $type, Uuid::v7(), $number, "Documento $number", $this->owner, $lines);

        return $this->inTransaction(fn () => $this->poster()->post($draft));
    }

    /** Voids an entry as a document's void handler would. */
    protected function reverse(Uuid $entry, string $date, ?Uuid $company = null): Uuid
    {
        return $this->inTransaction(fn () => $this->poster()->reverse($company ?? $this->company, $entry, new \DateTimeImmutable($date), $this->owner, 'Anulación'));
    }

    /**
     * Runs like a command handler on the bus: in a transaction, flushed on success, rolled back on a refusal.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    protected function inTransaction(callable $work): mixed
    {
        // What the test changed straight in the database is read afresh, as a new request would.
        $this->em()->clear();
        $db = $this->db();
        $db->beginTransaction();
        try {
            $result = $work();
            $this->em()->flush();
            $db->commit();

            return $result;
        } catch (\Throwable $e) {
            $db->rollBack();

            throw $e;
        } finally {
            $this->em()->clear();
        }
    }

    /** @return list<JournalEntry> */
    protected function entries(?Uuid $company = null): array
    {
        $this->em()->clear();

        return $this->em()->getRepository(JournalEntry::class)->findBy(['companyId' => $company ?? $this->company], ['number' => 'ASC']);
    }

    /**
     * A tercero with, optionally, its own receivable and payable accounts (§4.2 Cuentas contables).
     */
    protected function tercero(string $name = 'Cliente Uno', ?string $receivable = null, ?string $payable = null, ?Uuid $company = null): Uuid
    {
        $company ??= $this->company;
        $tercero = new Tercero($company, PersonType::Company, IdentificationType::Nit, (string) random_int(800000000, 899999999), null, $name, new \DateTimeImmutable());
        $this->save($tercero);
        $this->db()->update('tercero', [
            'receivable_account_id' => null === $receivable ? null : $this->account($receivable, $company)->toBinary(),
            'payable_account_id' => null === $payable ? null : $this->account($payable, $company)->toBinary(),
        ], ['id' => $tercero->id()->toBinary()]);

        return $tercero->id();
    }

    /** Signs a user of the company in with this role, instead of the owner. */
    protected function signInAs(string $role): void
    {
        $email = "$role@acme.co";
        $user = User::owner($this->company, $email, ucfirst($role), static::getContainer()->get(PasswordHasher::class)->hash('correct horse battery'), new \DateTimeImmutable());
        $this->save($user);
        $this->db()->update('app_user', ['role' => $role], ['id' => $user->id()->toBinary()]);
        $this->signOut();
        $this->signIn($email);
    }

    protected function lockBooks(string $until, ?Uuid $company = null): void
    {
        $this->db()->update('ledger_settings', ['locked_until' => $until], ['company_id' => ($company ?? $this->company)->toBinary()]);
    }

    protected static function line(string $side, string $amount, mixed $where, ?Uuid $tercero = null): EntryLine
    {
        $side = 'debit' === $side ? Side::Debit : Side::Credit;
        $money = Money::of($amount);

        return $where instanceof Uuid
            ? EntryLine::toAccount($side, $money, $where, $tercero)
            : EntryLine::toConcept($side, $money, $where, $tercero);
    }
}
