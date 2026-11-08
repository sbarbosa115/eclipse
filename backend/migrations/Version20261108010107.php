<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The observations-2026-11 contract (item 0, PRD §7.1, 7.2, 7.4–7.7): lines keep their retenciones in a table of
 * their own (the single embedded one moves there), and the new columns and tables the items build on.
 */
final class Version20261108010107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Observations 2026-11 contract: retenciones per line, product code on lines, municipality on terceros and the company, purchase-invoice correction columns, purchase orders, exógena concepts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE geo_municipality (code CHAR(5) NOT NULL, name VARCHAR(120) NOT NULL, department_code CHAR(2) NOT NULL, department_name VARCHAR(120) NOT NULL, INDEX geo_municipality_department (department_code, name), PRIMARY KEY (code)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE ledger_exogena_concept (id BINARY(16) NOT NULL, company_id BINARY(16) NOT NULL, format VARCHAR(4) NOT NULL, account_prefix VARCHAR(16) NOT NULL, concept_code VARCHAR(8) NOT NULL, UNIQUE INDEX ledger_exogena_concept_prefix (company_id, format, account_prefix), INDEX IDX_DA12116B979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE purchase_invoice_line_withholding (id BINARY(16) NOT NULL, base_amount NUMERIC(18, 2) NOT NULL, amount NUMERIC(18, 2) NOT NULL, company_id BINARY(16) NOT NULL, position INT NOT NULL, tax_id BINARY(16) DEFAULT NULL, name VARCHAR(80) NOT NULL, kind VARCHAR(20) NOT NULL, calculation VARCHAR(12) NOT NULL, value NUMERIC(18, 4) NOT NULL, account_id BINARY(16) DEFAULT NULL, line_id BINARY(16) NOT NULL, INDEX IDX_CB182B6A4D7B7542 (line_id), INDEX IDX_CB182B6A979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE purchase_order (id BINARY(16) NOT NULL, status VARCHAR(16) NOT NULL, prefix VARCHAR(10) DEFAULT NULL, sequence INT DEFAULT NULL, number VARCHAR(40) DEFAULT NULL, expected_date DATE DEFAULT NULL, notes VARCHAR(2000) DEFAULT NULL, converted_invoice_id BINARY(16) DEFAULT NULL, company_id BINARY(16) NOT NULL, tercero_id BINARY(16) NOT NULL, tercero_name VARCHAR(200) NOT NULL, issue_date DATE NOT NULL, created_by BINARY(16) NOT NULL, created_at DATETIME NOT NULL, emitted_by BINARY(16) DEFAULT NULL, emitted_at DATETIME DEFAULT NULL, voided_by BINARY(16) DEFAULT NULL, voided_at DATETIME DEFAULT NULL, void_reason VARCHAR(500) DEFAULT NULL, gross_total NUMERIC(18, 2) NOT NULL, discount_total NUMERIC(18, 2) NOT NULL, subtotal NUMERIC(18, 2) NOT NULL, tax_total NUMERIC(18, 2) NOT NULL, withholding_total NUMERIC(18, 2) NOT NULL, net_total NUMERIC(18, 2) NOT NULL, INDEX purchase_order_list (company_id, status, issue_date), INDEX purchase_order_tercero (company_id, tercero_id), INDEX IDX_21E210B2979B1AD6 (company_id), INDEX IDX_21E210B27F999FDB (tercero_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE purchase_order_line (id BINARY(16) NOT NULL, gross_amount NUMERIC(18, 2) NOT NULL, discount_amount NUMERIC(18, 2) NOT NULL, subtotal_amount NUMERIC(18, 2) NOT NULL, tax_amount NUMERIC(18, 2) NOT NULL, withholding_amount NUMERIC(18, 2) NOT NULL, total_amount NUMERIC(18, 2) NOT NULL, product_code VARCHAR(40) DEFAULT NULL, company_id BINARY(16) NOT NULL, position INT NOT NULL, product_id BINARY(16) DEFAULT NULL, account_id BINARY(16) DEFAULT NULL, description VARCHAR(500) NOT NULL, quantity NUMERIC(18, 4) NOT NULL, unit_price NUMERIC(18, 4) NOT NULL, discount NUMERIC(18, 4) NOT NULL, charge_tax_id BINARY(16) DEFAULT NULL, charge_name VARCHAR(80) NOT NULL, charge_kind VARCHAR(20) NOT NULL, charge_calculation VARCHAR(12) NOT NULL, charge_value NUMERIC(18, 4) NOT NULL, charge_account_id BINARY(16) DEFAULT NULL, document_id BINARY(16) NOT NULL, INDEX IDX_90D6D92BC33F7837 (document_id), INDEX IDX_90D6D92B979B1AD6 (company_id), INDEX IDX_90D6D92B4584665A (product_id), INDEX IDX_90D6D92B9B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE purchase_order_line_withholding (id BINARY(16) NOT NULL, base_amount NUMERIC(18, 2) NOT NULL, amount NUMERIC(18, 2) NOT NULL, company_id BINARY(16) NOT NULL, position INT NOT NULL, tax_id BINARY(16) DEFAULT NULL, name VARCHAR(80) NOT NULL, kind VARCHAR(20) NOT NULL, calculation VARCHAR(12) NOT NULL, value NUMERIC(18, 4) NOT NULL, account_id BINARY(16) DEFAULT NULL, line_id BINARY(16) NOT NULL, INDEX IDX_2268519F4D7B7542 (line_id), INDEX IDX_2268519F979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE quotation_line_withholding (id BINARY(16) NOT NULL, base_amount NUMERIC(18, 2) NOT NULL, amount NUMERIC(18, 2) NOT NULL, company_id BINARY(16) NOT NULL, position INT NOT NULL, tax_id BINARY(16) DEFAULT NULL, name VARCHAR(80) NOT NULL, kind VARCHAR(20) NOT NULL, calculation VARCHAR(12) NOT NULL, value NUMERIC(18, 4) NOT NULL, account_id BINARY(16) DEFAULT NULL, line_id BINARY(16) NOT NULL, INDEX IDX_59A6128B4D7B7542 (line_id), INDEX IDX_59A6128B979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE sales_invoice_line_withholding (id BINARY(16) NOT NULL, base_amount NUMERIC(18, 2) NOT NULL, amount NUMERIC(18, 2) NOT NULL, company_id BINARY(16) NOT NULL, position INT NOT NULL, tax_id BINARY(16) DEFAULT NULL, name VARCHAR(80) NOT NULL, kind VARCHAR(20) NOT NULL, calculation VARCHAR(12) NOT NULL, value NUMERIC(18, 4) NOT NULL, account_id BINARY(16) DEFAULT NULL, line_id BINARY(16) NOT NULL, INDEX IDX_3822F1DF4D7B7542 (line_id), INDEX IDX_3822F1DF979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE ledger_exogena_concept ADD CONSTRAINT fk_ledger_exogena_concept_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_invoice_line_withholding ADD CONSTRAINT FK_CB182B6A4D7B7542 FOREIGN KEY (line_id) REFERENCES purchase_invoice_line (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purchase_invoice_line_withholding ADD CONSTRAINT fk_purchase_invoice_line_withholding_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_order ADD CONSTRAINT fk_purchase_order_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_order ADD CONSTRAINT fk_purchase_order_tercero_id FOREIGN KEY (tercero_id) REFERENCES tercero (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_order_line ADD CONSTRAINT FK_90D6D92BC33F7837 FOREIGN KEY (document_id) REFERENCES purchase_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purchase_order_line ADD CONSTRAINT fk_purchase_order_line_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_order_line ADD CONSTRAINT fk_purchase_order_line_product_id FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_order_line ADD CONSTRAINT fk_purchase_order_line_account_id FOREIGN KEY (account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_order_line_withholding ADD CONSTRAINT FK_2268519F4D7B7542 FOREIGN KEY (line_id) REFERENCES purchase_order_line (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purchase_order_line_withholding ADD CONSTRAINT fk_purchase_order_line_withholding_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE quotation_line_withholding ADD CONSTRAINT FK_59A6128B4D7B7542 FOREIGN KEY (line_id) REFERENCES quotation_line (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quotation_line_withholding ADD CONSTRAINT fk_quotation_line_withholding_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE sales_invoice_line_withholding ADD CONSTRAINT FK_3822F1DF4D7B7542 FOREIGN KEY (line_id) REFERENCES sales_invoice_line (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sales_invoice_line_withholding ADD CONSTRAINT fk_sales_invoice_line_withholding_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE company ADD country_code CHAR(2) DEFAULT \'CO\' NOT NULL, ADD municipality_code CHAR(5) DEFAULT NULL');
        $this->addSql('DROP INDEX purchase_invoice_supplier_number ON purchase_invoice');
        $this->addSql('ALTER TABLE purchase_invoice ADD active_number_key VARCHAR(40) DEFAULT NULL, ADD corrected_from_id BINARY(16) DEFAULT NULL, ADD corrected_by_id BINARY(16) DEFAULT NULL, ADD purchase_order_id BINARY(16) DEFAULT NULL');
        // The live-number key: the supplier's number while the invoice is not voided (§4.6).
        $this->addSql("UPDATE purchase_invoice SET active_number_key = supplier_invoice_number WHERE status <> 'voided'");
        $this->addSql('CREATE UNIQUE INDEX purchase_invoice_active_number ON purchase_invoice (company_id, tercero_id, active_number_key)');
        // Each line's single retención becomes its first (and only) row; "no tax" (a null tax id) leaves none (§7.1).
        // Then each line keeps the product's código as it is today (§7.2).
        $this->addSql("INSERT INTO quotation_line_withholding (id, company_id, line_id, position, tax_id, name, kind, calculation, value, account_id, base_amount, amount) SELECT UNHEX(REPLACE(UUID(), '-', '')), l.company_id, l.id, 1, l.withholding_tax_id, l.withholding_name, l.withholding_kind, l.withholding_calculation, l.withholding_value, l.withholding_account_id, IF(l.withholding_kind = 'reteiva', l.tax_amount, l.subtotal_amount), l.withholding_amount FROM quotation_line l WHERE l.withholding_tax_id IS NOT NULL");
        $this->addSql('ALTER TABLE quotation_line ADD product_code VARCHAR(40) DEFAULT NULL');
        $this->addSql('UPDATE quotation_line l JOIN product p ON p.id = l.product_id SET l.product_code = p.code');
        $this->addSql("INSERT INTO sales_invoice_line_withholding (id, company_id, line_id, position, tax_id, name, kind, calculation, value, account_id, base_amount, amount) SELECT UNHEX(REPLACE(UUID(), '-', '')), l.company_id, l.id, 1, l.withholding_tax_id, l.withholding_name, l.withholding_kind, l.withholding_calculation, l.withholding_value, l.withholding_account_id, IF(l.withholding_kind = 'reteiva', l.tax_amount, l.subtotal_amount), l.withholding_amount FROM sales_invoice_line l WHERE l.withholding_tax_id IS NOT NULL");
        $this->addSql('ALTER TABLE sales_invoice_line ADD product_code VARCHAR(40) DEFAULT NULL');
        $this->addSql('UPDATE sales_invoice_line l JOIN product p ON p.id = l.product_id SET l.product_code = p.code');
        $this->addSql("INSERT INTO purchase_invoice_line_withholding (id, company_id, line_id, position, tax_id, name, kind, calculation, value, account_id, base_amount, amount) SELECT UNHEX(REPLACE(UUID(), '-', '')), l.company_id, l.id, 1, l.withholding_tax_id, l.withholding_name, l.withholding_kind, l.withholding_calculation, l.withholding_value, l.withholding_account_id, IF(l.withholding_kind = 'reteiva', l.tax_amount, l.subtotal_amount), l.withholding_amount FROM purchase_invoice_line l WHERE l.withholding_tax_id IS NOT NULL");
        $this->addSql('ALTER TABLE purchase_invoice_line ADD product_code VARCHAR(40) DEFAULT NULL');
        $this->addSql('UPDATE purchase_invoice_line l JOIN product p ON p.id = l.product_id SET l.product_code = p.code');
        $this->addSql('ALTER TABLE purchase_invoice_line DROP withholding_tax_id, DROP withholding_name, DROP withholding_kind, DROP withholding_calculation, DROP withholding_value, DROP withholding_account_id');
        $this->addSql('ALTER TABLE quotation_line DROP withholding_tax_id, DROP withholding_name, DROP withholding_kind, DROP withholding_calculation, DROP withholding_value, DROP withholding_account_id');
        $this->addSql('ALTER TABLE sales_invoice_line DROP withholding_tax_id, DROP withholding_name, DROP withholding_kind, DROP withholding_calculation, DROP withholding_value, DROP withholding_account_id');
        $this->addSql('ALTER TABLE tercero ADD country_code CHAR(2) DEFAULT \'CO\' NOT NULL, ADD municipality_code CHAR(5) DEFAULT NULL');
        $this->addSql('CREATE INDEX tercero_municipality ON tercero (company_id, municipality_code)');
        // Órdenes de compra are numbered OC-1, OC-2… in every existing company (new ones get it from the provisioner).
        $this->addSql("INSERT INTO numbering_series (id, company_id, kind, prefix, next_number) SELECT UNHEX(REPLACE(UUID(), '-', '')), c.id, 'purchase_order', 'OC', 1 FROM company c WHERE NOT EXISTS (SELECT 1 FROM numbering_series s WHERE s.company_id = c.id AND s.kind = 'purchase_order')");
    }

    public function down(Schema $schema): void
    {
        // A line with several retenciones cannot go back to one embedded retención without losing data.
        $this->throwIrreversibleMigrationException('Lines may hold several retenciones now; going back would drop all but one.');
    }
}
