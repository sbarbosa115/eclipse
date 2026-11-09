<?php

namespace App\Ledger\Application\Exogena;

use App\Ledger\Domain\Error\InvalidExogenaConcepts;
use App\Ledger\Domain\Model\ExogenaConcept;
use App\Ledger\Domain\Repository\ExogenaConceptRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;

/**
 * Replaces the table by difference: a row whose format and prefix stay keeps its record (its concept may change), the
 * rest are removed or added. (Removing everything and adding it back would trip the unique key: Doctrine inserts before
 * it deletes.) One audit line says what was added, changed and removed; saving the same table logs nothing.
 */
final class ReplaceExogenaConceptsHandler implements CommandHandler
{
    public function __construct(
        private readonly ExogenaConceptRepository $concepts,
        private readonly AuditTrail $audit,
    ) {
    }

    public function __invoke(ReplaceExogenaConcepts $command): void
    {
        $wanted = [];
        $violations = [];
        foreach ($command->rows as $i => $row) {
            $key = $row->format.' '.$row->accountPrefix;
            if (isset($wanted[$key])) {
                $violations[] = ['field' => "items[$i].account_prefix", 'message' => 'This prefix is already in the list of this format.'];
            }
            $wanted[$key] = $row;
        }
        if ([] !== $violations) {
            throw new InvalidExogenaConcepts($violations);
        }

        $added = $changed = $removed = [];
        foreach ($this->concepts->all($command->companyId) as $concept) {
            $key = $concept->format().' '.$concept->accountPrefix();
            if (!isset($wanted[$key])) {
                $removed[] = $key.' → '.$concept->conceptCode();
                $this->concepts->remove($concept);
                continue;
            }
            $to = $wanted[$key]->conceptCode;
            if ($to !== $concept->conceptCode()) {
                $changed[] = $key.': '.$concept->conceptCode().' → '.$to;
                $concept->reportUnder($to);
            }
            unset($wanted[$key]);
        }
        foreach ($wanted as $key => $row) {
            $added[] = $key.' → '.$row->conceptCode;
            $this->concepts->add(new ExogenaConcept($command->companyId, $row->format, $row->accountPrefix, $row->conceptCode));
        }

        if ([] !== $added || [] !== $changed || [] !== $removed) {
            sort($added);
            sort($changed);
            sort($removed);
            $this->audit->record($command->companyId, $command->userId, 'exogena_concepts.changed', 'exogena_concepts', null, ['added' => $added, 'changed' => $changed, 'removed' => $removed]);
        }
    }
}
