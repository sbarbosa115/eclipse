<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** The supplier's number of a purchase invoice is optional while it is a draft. */
final class Version20261003231059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Purchase invoices: a draft may be saved before the supplier\'s invoice number is known (emission still needs it).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE purchase_invoice CHANGE supplier_invoice_number supplier_invoice_number VARCHAR(40) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE purchase_invoice CHANGE supplier_invoice_number supplier_invoice_number VARCHAR(40) NOT NULL');
    }
}
