<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003212734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stage 1 (accounting): the schema of every context, fixed by item 0.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE access_token (
              id BINARY(16) NOT NULL,
              used_at DATETIME DEFAULT NULL,
              user_id BINARY(16) NOT NULL,
              purpose VARCHAR(20) NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              expires_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL,
              INDEX access_token_user (user_id, purpose),
              UNIQUE INDEX access_token_hash (token_hash),
              INDEX IDX_B6A2DD68A76ED395 (user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE app_user (
              id BINARY(16) NOT NULL,
              password_hash VARCHAR(255) DEFAULT NULL,
              status VARCHAR(12) NOT NULL,
              last_sign_in_at DATETIME DEFAULT NULL,
              company_id BINARY(16) NOT NULL,
              email VARCHAR(180) NOT NULL,
              name VARCHAR(120) NOT NULL,
              role VARCHAR(12) NOT NULL,
              created_at DATETIME NOT NULL,
              INDEX app_user_company (company_id, name),
              UNIQUE INDEX app_user_email (email),
              INDEX IDX_88BDF3E9979B1AD6 (company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE attachment (
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              owner_type VARCHAR(40) NOT NULL,
              owner_id BINARY(16) NOT NULL,
              file_name VARCHAR(255) NOT NULL,
              content_type VARCHAR(100) NOT NULL,
              size INT NOT NULL,
              uploaded_by BINARY(16) NOT NULL,
              uploaded_at DATETIME NOT NULL,
              INDEX attachment_owner (company_id, owner_type, owner_id),
              INDEX IDX_795FD9BB979B1AD6 (company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_log (
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              user_id BINARY(16) DEFAULT NULL,
              action VARCHAR(80) NOT NULL,
              subject_type VARCHAR(40) NOT NULL,
              subject_id BINARY(16) DEFAULT NULL,
              data JSON NOT NULL,
              occurred_at DATETIME NOT NULL,
              INDEX audit_log_company_at (company_id, occurred_at),
              INDEX IDX_F6E1C0F5979B1AD6 (company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cash_receipt (
              created_by BINARY(16) NOT NULL,
              created_at DATETIME NOT NULL,
              emitted_by BINARY(16) DEFAULT NULL,
              emitted_at DATETIME DEFAULT NULL,
              voided_by BINARY(16) DEFAULT NULL,
              voided_at DATETIME DEFAULT NULL,
              void_reason VARCHAR(500) DEFAULT NULL,
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              prefix VARCHAR(10) NOT NULL,
              sequence INT NOT NULL,
              number VARCHAR(40) NOT NULL,
              tercero_id BINARY(16) NOT NULL,
              tercero_name VARCHAR(200) NOT NULL,
              receipt_date DATE NOT NULL,
              payment_method_id BINARY(16) NOT NULL,
              method_name VARCHAR(80) NOT NULL,
              account_id BINARY(16) NOT NULL,
              amount NUMERIC(18, 2) NOT NULL,
              notes VARCHAR(2000) DEFAULT NULL,
              journal_entry_id BINARY(16) DEFAULT NULL,
              reversal_entry_id BINARY(16) DEFAULT NULL,
              INDEX cash_receipt_list (company_id, status, receipt_date),
              UNIQUE INDEX cash_receipt_number (company_id, prefix, sequence),
              INDEX IDX_D75A419979B1AD6 (company_id),
              INDEX IDX_D75A4197F999FDB (tercero_id),
              INDEX IDX_D75A4195AA1164F (payment_method_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cash_receipt_allocation (
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              open_item_id BINARY(16) NOT NULL,
              invoice_id BINARY(16) NOT NULL,
              invoice_number VARCHAR(40) NOT NULL,
              amount NUMERIC(18, 2) NOT NULL,
              receipt_id BINARY(16) NOT NULL,
              INDEX cash_receipt_allocation_invoice (company_id, invoice_id),
              INDEX IDX_6B3610FC2B5CA896 (receipt_id),
              INDEX IDX_6B3610FC979B1AD6 (company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE company (
              id BINARY(16) NOT NULL,
              trade_name VARCHAR(180) DEFAULT NULL,
              person_type VARCHAR(12) NOT NULL,
              address VARCHAR(200) DEFAULT NULL,
              city VARCHAR(100) DEFAULT NULL,
              phone VARCHAR(40) DEFAULT NULL,
              email VARCHAR(180) DEFAULT NULL,
              logo_id BINARY(16) DEFAULT NULL,
              vat_regime VARCHAR(16) NOT NULL,
              fiscal_responsibilities JSON NOT NULL,
              default_charge_tax_id BINARY(16) DEFAULT NULL,
              default_withholding_tax_id BINARY(16) DEFAULT NULL,
              resolution_warning_numbers INT NOT NULL,
              resolution_warning_days INT NOT NULL,
              manual_invoicing_confirmed_by BINARY(16) DEFAULT NULL,
              manual_invoicing_confirmed_at DATETIME DEFAULT NULL,
              legal_name VARCHAR(180) NOT NULL,
              identification_type VARCHAR(12) NOT NULL,
              identification_number VARCHAR(20) NOT NULL,
              check_digit VARCHAR(1) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE invoicing_resolution (
              id BINARY(16) NOT NULL,
              next_number INT NOT NULL,
              company_id BINARY(16) NOT NULL,
              resolution_number VARCHAR(40) NOT NULL,
              prefix VARCHAR(10) NOT NULL,
              range_from INT NOT NULL,
              range_to INT NOT NULL,
              valid_from DATE NOT NULL,
              valid_to DATE NOT NULL,
              mode VARCHAR(12) NOT NULL,
              INDEX invoicing_resolution_company (company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE journal_entry (
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              number INT NOT NULL,
              entry_date DATE NOT NULL,
              source_type VARCHAR(30) NOT NULL,
              source_id BINARY(16) NOT NULL,
              source_number VARCHAR(40) NOT NULL,
              description VARCHAR(255) NOT NULL,
              reverses_id BINARY(16) DEFAULT NULL,
              created_by BINARY(16) NOT NULL,
              created_at DATETIME NOT NULL,
              INDEX journal_entry_date (company_id, entry_date),
              INDEX journal_entry_source (
                company_id, source_type, source_id
              ),
              UNIQUE INDEX journal_entry_number (company_id, number),
              INDEX IDX_C8FAAE5A979B1AD6 (company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE journal_line (
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              position INT NOT NULL,
              account_id BINARY(16) NOT NULL,
              account_code VARCHAR(16) NOT NULL,
              tercero_id BINARY(16) DEFAULT NULL,
              debit NUMERIC(18, 2) NOT NULL,
              credit NUMERIC(18, 2) NOT NULL,
              description VARCHAR(255) DEFAULT NULL,
              entry_id BINARY(16) NOT NULL,
              INDEX journal_line_account (company_id, account_code),
              INDEX journal_line_tercero (
                company_id, tercero_id, account_code
              ),
              INDEX IDX_692C8C7EBA364942 (entry_id),
              INDEX IDX_692C8C7E979B1AD6 (company_id),
              INDEX IDX_692C8C7E9B6B5FBA (account_id),
              INDEX IDX_692C8C7E7F999FDB (tercero_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE ledger_account (
              id BINARY(16) NOT NULL,
              level VARCHAR(12) NOT NULL,
              active TINYINT NOT NULL,
              company_id BINARY(16) NOT NULL,
              code VARCHAR(16) NOT NULL,
              name VARCHAR(200) NOT NULL,
              nature VARCHAR(8) NOT NULL,
              parent_code VARCHAR(16) DEFAULT NULL,
              standard TINYINT NOT NULL,
              usable_on_purchases TINYINT NOT NULL,
              INDEX ledger_account_parent (company_id, parent_code),
              UNIQUE INDEX ledger_account_code (company_id, code),
              INDEX IDX_B3339695979B1AD6 (company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE ledger_settings (
              locked_until DATE DEFAULT NULL,
              company_id BINARY(16) NOT NULL,
              PRIMARY KEY (company_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE numbering_series (
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              kind VARCHAR(30) NOT NULL,
              prefix VARCHAR(10) NOT NULL,
              next_number INT NOT NULL,
              UNIQUE INDEX numbering_series_kind (company_id, kind),
              INDEX IDX_BEA6D1ED979B1AD6 (company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE payable (
              id BINARY(16) NOT NULL,
              balance NUMERIC(18, 2) NOT NULL,
              voided TINYINT NOT NULL,
              company_id BINARY(16) NOT NULL,
              invoice_id BINARY(16) NOT NULL,
              invoice_number VARCHAR(40) NOT NULL,
              tercero_id BINARY(16) NOT NULL,
              issue_date DATE NOT NULL,
              due_date DATE NOT NULL,
              amount NUMERIC(18, 2) NOT NULL,
              INDEX payable_open (
                company_id, tercero_id, voided, due_date
              ),
              INDEX payable_invoice (company_id, invoice_id),
              INDEX IDX_7A376F40979B1AD6 (company_id),
              INDEX IDX_7A376F407F999FDB (tercero_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE payment_method (
              id BINARY(16) NOT NULL,
              active TINYINT NOT NULL,
              company_id BINARY(16) NOT NULL,
              name VARCHAR(80) NOT NULL,
              kind VARCHAR(8) NOT NULL,
              account_id BINARY(16) DEFAULT NULL,
              standard TINYINT NOT NULL,
              INDEX payment_method_company (company_id, active),
              INDEX IDX_7B61A1F6979B1AD6 (company_id),
              INDEX IDX_7B61A1F69B6B5FBA (account_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE posting_rule (
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              concept VARCHAR(40) NOT NULL,
              account_id BINARY(16) NOT NULL,
              UNIQUE INDEX posting_rule_concept (company_id, concept),
              INDEX IDX_F0811127979B1AD6 (company_id),
              INDEX IDX_F08111279B6B5FBA (account_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product (
              id BINARY(16) NOT NULL,
              category_id BINARY(16) DEFAULT NULL,
              description VARCHAR(2000) DEFAULT NULL,
              active TINYINT NOT NULL,
              company_id BINARY(16) NOT NULL,
              type VARCHAR(12) NOT NULL,
              code VARCHAR(40) NOT NULL,
              name VARCHAR(200) NOT NULL,
              unit_code VARCHAR(8) NOT NULL,
              sale_price NUMERIC(18, 4) NOT NULL,
              price_includes_tax TINYINT NOT NULL,
              charge_tax_id BINARY(16) DEFAULT NULL,
              withholding_tax_id BINARY(16) DEFAULT NULL,
              revenue_account_id BINARY(16) DEFAULT NULL,
              expense_account_id BINARY(16) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              INDEX product_company_name (company_id, name),
              UNIQUE INDEX product_code (company_id, code),
              INDEX IDX_D34A04AD979B1AD6 (company_id),
              INDEX IDX_D34A04AD12469DE2 (category_id),
              INDEX IDX_D34A04AD870BA82E (charge_tax_id),
              INDEX IDX_D34A04AD35366E04 (withholding_tax_id),
              INDEX IDX_D34A04AD1269BA99 (revenue_account_id),
              INDEX IDX_D34A04AD2B7F60B3 (expense_account_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product_category (
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              name VARCHAR(100) NOT NULL,
              UNIQUE INDEX product_category_name (company_id, name),
              INDEX IDX_CDFC7356979B1AD6 (company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE purchase_invoice (
              id BINARY(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              prefix VARCHAR(10) DEFAULT NULL,
              sequence INT DEFAULT NULL,
              number VARCHAR(40) DEFAULT NULL,
              due_date DATE DEFAULT NULL,
              notes VARCHAR(2000) DEFAULT NULL,
              paid_amount NUMERIC(18, 2) NOT NULL,
              journal_entry_id BINARY(16) DEFAULT NULL,
              reversal_entry_id BINARY(16) DEFAULT NULL,
              company_id BINARY(16) NOT NULL,
              tercero_id BINARY(16) NOT NULL,
              tercero_name VARCHAR(200) NOT NULL,
              supplier_invoice_number VARCHAR(40) NOT NULL,
              issue_date DATE NOT NULL,
              created_by BINARY(16) NOT NULL,
              created_at DATETIME NOT NULL,
              emitted_by BINARY(16) DEFAULT NULL,
              emitted_at DATETIME DEFAULT NULL,
              voided_by BINARY(16) DEFAULT NULL,
              voided_at DATETIME DEFAULT NULL,
              void_reason VARCHAR(500) DEFAULT NULL,
              gross_total NUMERIC(18, 2) NOT NULL,
              discount_total NUMERIC(18, 2) NOT NULL,
              subtotal NUMERIC(18, 2) NOT NULL,
              tax_total NUMERIC(18, 2) NOT NULL,
              withholding_total NUMERIC(18, 2) NOT NULL,
              net_total NUMERIC(18, 2) NOT NULL,
              INDEX purchase_invoice_list (company_id, status, issue_date),
              UNIQUE INDEX purchase_invoice_supplier_number (
                company_id, tercero_id, supplier_invoice_number
              ),
              INDEX IDX_8BBFDD3D979B1AD6 (company_id),
              INDEX IDX_8BBFDD3D6A86E4FB (journal_entry_id),
              INDEX IDX_8BBFDD3D7F999FDB (tercero_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE purchase_invoice_line (
              id BINARY(16) NOT NULL,
              gross_amount NUMERIC(18, 2) NOT NULL,
              discount_amount NUMERIC(18, 2) NOT NULL,
              subtotal_amount NUMERIC(18, 2) NOT NULL,
              tax_amount NUMERIC(18, 2) NOT NULL,
              withholding_amount NUMERIC(18, 2) NOT NULL,
              total_amount NUMERIC(18, 2) NOT NULL,
              company_id BINARY(16) NOT NULL,
              position INT NOT NULL,
              product_id BINARY(16) DEFAULT NULL,
              account_id BINARY(16) DEFAULT NULL,
              description VARCHAR(500) NOT NULL,
              quantity NUMERIC(18, 4) NOT NULL,
              unit_price NUMERIC(18, 4) NOT NULL,
              discount NUMERIC(18, 4) NOT NULL,
              charge_tax_id BINARY(16) DEFAULT NULL,
              charge_name VARCHAR(80) NOT NULL,
              charge_kind VARCHAR(20) NOT NULL,
              charge_calculation VARCHAR(12) NOT NULL,
              charge_value NUMERIC(18, 4) NOT NULL,
              charge_account_id BINARY(16) DEFAULT NULL,
              withholding_tax_id BINARY(16) DEFAULT NULL,
              withholding_name VARCHAR(80) NOT NULL,
              withholding_kind VARCHAR(20) NOT NULL,
              withholding_calculation VARCHAR(12) NOT NULL,
              withholding_value NUMERIC(18, 4) NOT NULL,
              withholding_account_id BINARY(16) DEFAULT NULL,
              document_id BINARY(16) NOT NULL,
              INDEX IDX_17CB2133C33F7837 (document_id),
              INDEX IDX_17CB2133979B1AD6 (company_id),
              INDEX IDX_17CB21334584665A (product_id),
              INDEX IDX_17CB21339B6B5FBA (account_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE purchase_invoice_payment (
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              position INT NOT NULL,
              payment_method_id BINARY(16) NOT NULL,
              method_name VARCHAR(80) NOT NULL,
              kind VARCHAR(8) NOT NULL,
              account_id BINARY(16) DEFAULT NULL,
              amount NUMERIC(18, 2) NOT NULL,
              due_date DATE DEFAULT NULL,
              invoice_id BINARY(16) NOT NULL,
              INDEX IDX_E016617A2989F1FD (invoice_id),
              INDEX IDX_E016617A979B1AD6 (company_id),
              INDEX IDX_E016617A5AA1164F (payment_method_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE quotation (
              id BINARY(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              prefix VARCHAR(10) DEFAULT NULL,
              sequence INT DEFAULT NULL,
              number VARCHAR(40) DEFAULT NULL,
              contact_id BINARY(16) DEFAULT NULL,
              responsible_id BINARY(16) DEFAULT NULL,
              header LONGTEXT DEFAULT NULL,
              terms LONGTEXT DEFAULT NULL,
              notes VARCHAR(2000) DEFAULT NULL,
              converted_invoice_id BINARY(16) DEFAULT NULL,
              company_id BINARY(16) NOT NULL,
              tercero_id BINARY(16) NOT NULL,
              tercero_name VARCHAR(200) NOT NULL,
              issue_date DATE NOT NULL,
              expiry_date DATE NOT NULL,
              created_by BINARY(16) NOT NULL,
              created_at DATETIME NOT NULL,
              emitted_by BINARY(16) DEFAULT NULL,
              emitted_at DATETIME DEFAULT NULL,
              voided_by BINARY(16) DEFAULT NULL,
              voided_at DATETIME DEFAULT NULL,
              void_reason VARCHAR(500) DEFAULT NULL,
              gross_total NUMERIC(18, 2) NOT NULL,
              discount_total NUMERIC(18, 2) NOT NULL,
              subtotal NUMERIC(18, 2) NOT NULL,
              tax_total NUMERIC(18, 2) NOT NULL,
              withholding_total NUMERIC(18, 2) NOT NULL,
              net_total NUMERIC(18, 2) NOT NULL,
              INDEX quotation_list (company_id, status, issue_date),
              INDEX quotation_tercero (company_id, tercero_id),
              INDEX IDX_474A8DB9979B1AD6 (company_id),
              INDEX IDX_474A8DB97F999FDB (tercero_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE quotation_line (
              id BINARY(16) NOT NULL,
              gross_amount NUMERIC(18, 2) NOT NULL,
              discount_amount NUMERIC(18, 2) NOT NULL,
              subtotal_amount NUMERIC(18, 2) NOT NULL,
              tax_amount NUMERIC(18, 2) NOT NULL,
              withholding_amount NUMERIC(18, 2) NOT NULL,
              total_amount NUMERIC(18, 2) NOT NULL,
              company_id BINARY(16) NOT NULL,
              position INT NOT NULL,
              product_id BINARY(16) DEFAULT NULL,
              account_id BINARY(16) DEFAULT NULL,
              description VARCHAR(500) NOT NULL,
              quantity NUMERIC(18, 4) NOT NULL,
              unit_price NUMERIC(18, 4) NOT NULL,
              discount NUMERIC(18, 4) NOT NULL,
              charge_tax_id BINARY(16) DEFAULT NULL,
              charge_name VARCHAR(80) NOT NULL,
              charge_kind VARCHAR(20) NOT NULL,
              charge_calculation VARCHAR(12) NOT NULL,
              charge_value NUMERIC(18, 4) NOT NULL,
              charge_account_id BINARY(16) DEFAULT NULL,
              withholding_tax_id BINARY(16) DEFAULT NULL,
              withholding_name VARCHAR(80) NOT NULL,
              withholding_kind VARCHAR(20) NOT NULL,
              withholding_calculation VARCHAR(12) NOT NULL,
              withholding_value NUMERIC(18, 4) NOT NULL,
              withholding_account_id BINARY(16) DEFAULT NULL,
              document_id BINARY(16) NOT NULL,
              INDEX IDX_4CE011BAC33F7837 (document_id),
              INDEX IDX_4CE011BA979B1AD6 (company_id),
              INDEX IDX_4CE011BA4584665A (product_id),
              INDEX IDX_4CE011BA9B6B5FBA (account_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE receivable (
              id BINARY(16) NOT NULL,
              balance NUMERIC(18, 2) NOT NULL,
              voided TINYINT NOT NULL,
              company_id BINARY(16) NOT NULL,
              invoice_id BINARY(16) NOT NULL,
              invoice_number VARCHAR(40) NOT NULL,
              tercero_id BINARY(16) NOT NULL,
              issue_date DATE NOT NULL,
              due_date DATE NOT NULL,
              amount NUMERIC(18, 2) NOT NULL,
              INDEX receivable_open (
                company_id, tercero_id, voided, due_date
              ),
              INDEX receivable_invoice (company_id, invoice_id),
              INDEX IDX_E518488B979B1AD6 (company_id),
              INDEX IDX_E518488B7F999FDB (tercero_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE sales_invoice (
              id BINARY(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              resolution_id BINARY(16) DEFAULT NULL,
              prefix VARCHAR(10) DEFAULT NULL,
              authorised_number INT DEFAULT NULL,
              internal_number INT DEFAULT NULL,
              number VARCHAR(40) DEFAULT NULL,
              contact_id BINARY(16) DEFAULT NULL,
              seller_id BINARY(16) DEFAULT NULL,
              quotation_id BINARY(16) DEFAULT NULL,
              notes VARCHAR(2000) DEFAULT NULL,
              paid_amount NUMERIC(18, 2) NOT NULL,
              journal_entry_id BINARY(16) DEFAULT NULL,
              reversal_entry_id BINARY(16) DEFAULT NULL,
              company_id BINARY(16) NOT NULL,
              tercero_id BINARY(16) NOT NULL,
              tercero_name VARCHAR(200) NOT NULL,
              issue_date DATE NOT NULL,
              created_by BINARY(16) NOT NULL,
              created_at DATETIME NOT NULL,
              emitted_by BINARY(16) DEFAULT NULL,
              emitted_at DATETIME DEFAULT NULL,
              voided_by BINARY(16) DEFAULT NULL,
              voided_at DATETIME DEFAULT NULL,
              void_reason VARCHAR(500) DEFAULT NULL,
              gross_total NUMERIC(18, 2) NOT NULL,
              discount_total NUMERIC(18, 2) NOT NULL,
              subtotal NUMERIC(18, 2) NOT NULL,
              tax_total NUMERIC(18, 2) NOT NULL,
              withholding_total NUMERIC(18, 2) NOT NULL,
              net_total NUMERIC(18, 2) NOT NULL,
              INDEX sales_invoice_list (company_id, status, issue_date),
              INDEX sales_invoice_tercero (company_id, tercero_id),
              UNIQUE INDEX sales_invoice_number (
                company_id, resolution_id, authorised_number
              ),
              INDEX IDX_9E9C6B24979B1AD6 (company_id),
              INDEX IDX_9E9C6B2412A1C43A (resolution_id),
              INDEX IDX_9E9C6B246A86E4FB (journal_entry_id),
              INDEX IDX_9E9C6B247F999FDB (tercero_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE sales_invoice_line (
              id BINARY(16) NOT NULL,
              gross_amount NUMERIC(18, 2) NOT NULL,
              discount_amount NUMERIC(18, 2) NOT NULL,
              subtotal_amount NUMERIC(18, 2) NOT NULL,
              tax_amount NUMERIC(18, 2) NOT NULL,
              withholding_amount NUMERIC(18, 2) NOT NULL,
              total_amount NUMERIC(18, 2) NOT NULL,
              company_id BINARY(16) NOT NULL,
              position INT NOT NULL,
              product_id BINARY(16) DEFAULT NULL,
              account_id BINARY(16) DEFAULT NULL,
              description VARCHAR(500) NOT NULL,
              quantity NUMERIC(18, 4) NOT NULL,
              unit_price NUMERIC(18, 4) NOT NULL,
              discount NUMERIC(18, 4) NOT NULL,
              charge_tax_id BINARY(16) DEFAULT NULL,
              charge_name VARCHAR(80) NOT NULL,
              charge_kind VARCHAR(20) NOT NULL,
              charge_calculation VARCHAR(12) NOT NULL,
              charge_value NUMERIC(18, 4) NOT NULL,
              charge_account_id BINARY(16) DEFAULT NULL,
              withholding_tax_id BINARY(16) DEFAULT NULL,
              withholding_name VARCHAR(80) NOT NULL,
              withholding_kind VARCHAR(20) NOT NULL,
              withholding_calculation VARCHAR(12) NOT NULL,
              withholding_value NUMERIC(18, 4) NOT NULL,
              withholding_account_id BINARY(16) DEFAULT NULL,
              document_id BINARY(16) NOT NULL,
              INDEX IDX_4F9CDB2BC33F7837 (document_id),
              INDEX IDX_4F9CDB2B979B1AD6 (company_id),
              INDEX IDX_4F9CDB2B4584665A (product_id),
              INDEX IDX_4F9CDB2B9B6B5FBA (account_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE sales_invoice_payment (
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              position INT NOT NULL,
              payment_method_id BINARY(16) NOT NULL,
              method_name VARCHAR(80) NOT NULL,
              kind VARCHAR(8) NOT NULL,
              account_id BINARY(16) DEFAULT NULL,
              amount NUMERIC(18, 2) NOT NULL,
              due_date DATE DEFAULT NULL,
              invoice_id BINARY(16) NOT NULL,
              INDEX IDX_E97F5E8A2989F1FD (invoice_id),
              INDEX IDX_E97F5E8A979B1AD6 (company_id),
              INDEX IDX_E97F5E8A5AA1164F (payment_method_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_payment (
              created_by BINARY(16) NOT NULL,
              created_at DATETIME NOT NULL,
              emitted_by BINARY(16) DEFAULT NULL,
              emitted_at DATETIME DEFAULT NULL,
              voided_by BINARY(16) DEFAULT NULL,
              voided_at DATETIME DEFAULT NULL,
              void_reason VARCHAR(500) DEFAULT NULL,
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              status VARCHAR(16) NOT NULL,
              prefix VARCHAR(10) NOT NULL,
              sequence INT NOT NULL,
              number VARCHAR(40) NOT NULL,
              tercero_id BINARY(16) NOT NULL,
              tercero_name VARCHAR(200) NOT NULL,
              receipt_date DATE NOT NULL,
              payment_method_id BINARY(16) NOT NULL,
              method_name VARCHAR(80) NOT NULL,
              account_id BINARY(16) NOT NULL,
              amount NUMERIC(18, 2) NOT NULL,
              notes VARCHAR(2000) DEFAULT NULL,
              journal_entry_id BINARY(16) DEFAULT NULL,
              reversal_entry_id BINARY(16) DEFAULT NULL,
              INDEX supplier_payment_list (company_id, status, receipt_date),
              UNIQUE INDEX supplier_payment_number (company_id, prefix, sequence),
              INDEX IDX_EC4DF012979B1AD6 (company_id),
              INDEX IDX_EC4DF0127F999FDB (tercero_id),
              INDEX IDX_EC4DF0125AA1164F (payment_method_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_payment_allocation (
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              open_item_id BINARY(16) NOT NULL,
              invoice_id BINARY(16) NOT NULL,
              invoice_number VARCHAR(40) NOT NULL,
              amount NUMERIC(18, 2) NOT NULL,
              payment_id BINARY(16) NOT NULL,
              INDEX supplier_payment_allocation_invoice (company_id, invoice_id),
              INDEX IDX_227BF6BF4C3A3BB (payment_id),
              INDEX IDX_227BF6BF979B1AD6 (company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE tax (
              id BINARY(16) NOT NULL,
              active TINYINT NOT NULL,
              company_id BINARY(16) NOT NULL,
              name VARCHAR(80) NOT NULL,
              tax_class VARCHAR(12) NOT NULL,
              kind VARCHAR(12) NOT NULL,
              calculation VARCHAR(12) NOT NULL,
              rate NUMERIC(18, 4) NOT NULL,
              sales_account_id BINARY(16) DEFAULT NULL,
              purchase_account_id BINARY(16) DEFAULT NULL,
              valid_from DATE DEFAULT NULL,
              valid_to DATE DEFAULT NULL,
              standard TINYINT NOT NULL,
              INDEX tax_company_class (company_id, tax_class, active),
              INDEX IDX_8E81BA76979B1AD6 (company_id),
              INDEX IDX_8E81BA769457E8D6 (sales_account_id),
              INDEX IDX_8E81BA7697580870 (purchase_account_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE tercero (
              id BINARY(16) NOT NULL,
              display_name VARCHAR(200) NOT NULL,
              first_names VARCHAR(120) DEFAULT NULL,
              last_names VARCHAR(120) DEFAULT NULL,
              business_name VARCHAR(200) DEFAULT NULL,
              trade_name VARCHAR(200) DEFAULT NULL,
              city VARCHAR(100) DEFAULT NULL,
              address VARCHAR(200) DEFAULT NULL,
              phones JSON NOT NULL,
              billing_contact_name VARCHAR(160) DEFAULT NULL,
              email VARCHAR(180) DEFAULT NULL,
              mobile VARCHAR(30) DEFAULT NULL,
              postal_code VARCHAR(12) DEFAULT NULL,
              vat_regime VARCHAR(16) DEFAULT NULL,
              billing_contact_is_payer TINYINT NOT NULL,
              fiscal_responsibilities JSON NOT NULL,
              is_client TINYINT NOT NULL,
              is_supplier TINYINT NOT NULL,
              is_employee TINYINT NOT NULL,
              is_other TINYINT NOT NULL,
              receivable_account_id BINARY(16) DEFAULT NULL,
              payable_account_id BINARY(16) DEFAULT NULL,
              active TINYINT NOT NULL,
              erased_at DATETIME DEFAULT NULL,
              company_id BINARY(16) NOT NULL,
              person_type VARCHAR(12) NOT NULL,
              identification_type VARCHAR(16) NOT NULL,
              identification_number VARCHAR(20) NOT NULL,
              check_digit VARCHAR(1) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              branch_code VARCHAR(6) NOT NULL,
              INDEX tercero_company_name (company_id, display_name),
              UNIQUE INDEX tercero_identification (
                company_id, identification_type,
                identification_number, branch_code
              ),
              INDEX IDX_F9EB309B979B1AD6 (company_id),
              INDEX IDX_F9EB309BFDA59121 (receivable_account_id),
              INDEX IDX_F9EB309B7944C993 (payable_account_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE tercero_contact (
              id BINARY(16) NOT NULL,
              company_id BINARY(16) NOT NULL,
              name VARCHAR(160) NOT NULL,
              email VARCHAR(180) DEFAULT NULL,
              phone VARCHAR(30) DEFAULT NULL,
              tercero_id BINARY(16) NOT NULL,
              INDEX IDX_DB04D3DD7F999FDB (tercero_id),
              INDEX IDX_DB04D3DD979B1AD6 (company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
              id BIGINT AUTO_INCREMENT NOT NULL,
              body LONGTEXT NOT NULL,
              headers LONGTEXT NOT NULL,
              queue_name VARCHAR(190) NOT NULL,
              created_at DATETIME NOT NULL,
              available_at DATETIME NOT NULL,
              delivered_at DATETIME DEFAULT NULL,
              INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (
                queue_name, available_at, delivered_at,
                id
              ),
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              access_token
            ADD
              CONSTRAINT fk_access_token_user_id FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              app_user
            ADD
              CONSTRAINT fk_app_user_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              attachment
            ADD
              CONSTRAINT fk_attachment_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              audit_log
            ADD
              CONSTRAINT fk_audit_log_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cash_receipt
            ADD
              CONSTRAINT fk_cash_receipt_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cash_receipt
            ADD
              CONSTRAINT fk_cash_receipt_tercero_id FOREIGN KEY (tercero_id) REFERENCES tercero (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cash_receipt
            ADD
              CONSTRAINT fk_cash_receipt_payment_method_id FOREIGN KEY (payment_method_id) REFERENCES payment_method (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cash_receipt_allocation
            ADD
              CONSTRAINT FK_6B3610FC2B5CA896 FOREIGN KEY (receipt_id) REFERENCES cash_receipt (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cash_receipt_allocation
            ADD
              CONSTRAINT fk_cash_receipt_allocation_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              invoicing_resolution
            ADD
              CONSTRAINT fk_invoicing_resolution_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              journal_entry
            ADD
              CONSTRAINT fk_journal_entry_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              journal_line
            ADD
              CONSTRAINT FK_692C8C7EBA364942 FOREIGN KEY (entry_id) REFERENCES journal_entry (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              journal_line
            ADD
              CONSTRAINT fk_journal_line_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              journal_line
            ADD
              CONSTRAINT fk_journal_line_account_id FOREIGN KEY (account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              journal_line
            ADD
              CONSTRAINT fk_journal_line_tercero_id FOREIGN KEY (tercero_id) REFERENCES tercero (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              ledger_account
            ADD
              CONSTRAINT fk_ledger_account_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              ledger_settings
            ADD
              CONSTRAINT fk_ledger_settings_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              numbering_series
            ADD
              CONSTRAINT fk_numbering_series_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              payable
            ADD
              CONSTRAINT fk_payable_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              payable
            ADD
              CONSTRAINT fk_payable_tercero_id FOREIGN KEY (tercero_id) REFERENCES tercero (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              payment_method
            ADD
              CONSTRAINT fk_payment_method_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              payment_method
            ADD
              CONSTRAINT fk_payment_method_account_id FOREIGN KEY (account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              posting_rule
            ADD
              CONSTRAINT fk_posting_rule_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              posting_rule
            ADD
              CONSTRAINT fk_posting_rule_account_id FOREIGN KEY (account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product
            ADD
              CONSTRAINT fk_product_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product
            ADD
              CONSTRAINT fk_product_category_id FOREIGN KEY (category_id) REFERENCES product_category (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product
            ADD
              CONSTRAINT fk_product_charge_tax_id FOREIGN KEY (charge_tax_id) REFERENCES tax (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product
            ADD
              CONSTRAINT fk_product_withholding_tax_id FOREIGN KEY (withholding_tax_id) REFERENCES tax (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product
            ADD
              CONSTRAINT fk_product_revenue_account_id FOREIGN KEY (revenue_account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product
            ADD
              CONSTRAINT fk_product_expense_account_id FOREIGN KEY (expense_account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_category
            ADD
              CONSTRAINT fk_product_category_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              purchase_invoice
            ADD
              CONSTRAINT fk_purchase_invoice_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              purchase_invoice
            ADD
              CONSTRAINT fk_purchase_invoice_journal_entry_id FOREIGN KEY (journal_entry_id) REFERENCES journal_entry (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              purchase_invoice
            ADD
              CONSTRAINT fk_purchase_invoice_tercero_id FOREIGN KEY (tercero_id) REFERENCES tercero (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              purchase_invoice_line
            ADD
              CONSTRAINT FK_17CB2133C33F7837 FOREIGN KEY (document_id) REFERENCES purchase_invoice (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              purchase_invoice_line
            ADD
              CONSTRAINT fk_purchase_invoice_line_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              purchase_invoice_line
            ADD
              CONSTRAINT fk_purchase_invoice_line_product_id FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              purchase_invoice_line
            ADD
              CONSTRAINT fk_purchase_invoice_line_account_id FOREIGN KEY (account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              purchase_invoice_payment
            ADD
              CONSTRAINT FK_E016617A2989F1FD FOREIGN KEY (invoice_id) REFERENCES purchase_invoice (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              purchase_invoice_payment
            ADD
              CONSTRAINT fk_purchase_invoice_payment_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              purchase_invoice_payment
            ADD
              CONSTRAINT fk_purchase_invoice_payment_payment_method_id FOREIGN KEY (payment_method_id) REFERENCES payment_method (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              quotation
            ADD
              CONSTRAINT fk_quotation_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              quotation
            ADD
              CONSTRAINT fk_quotation_tercero_id FOREIGN KEY (tercero_id) REFERENCES tercero (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              quotation_line
            ADD
              CONSTRAINT FK_4CE011BAC33F7837 FOREIGN KEY (document_id) REFERENCES quotation (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              quotation_line
            ADD
              CONSTRAINT fk_quotation_line_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              quotation_line
            ADD
              CONSTRAINT fk_quotation_line_product_id FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              quotation_line
            ADD
              CONSTRAINT fk_quotation_line_account_id FOREIGN KEY (account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              receivable
            ADD
              CONSTRAINT fk_receivable_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              receivable
            ADD
              CONSTRAINT fk_receivable_tercero_id FOREIGN KEY (tercero_id) REFERENCES tercero (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sales_invoice
            ADD
              CONSTRAINT fk_sales_invoice_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sales_invoice
            ADD
              CONSTRAINT fk_sales_invoice_resolution_id FOREIGN KEY (resolution_id) REFERENCES invoicing_resolution (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sales_invoice
            ADD
              CONSTRAINT fk_sales_invoice_journal_entry_id FOREIGN KEY (journal_entry_id) REFERENCES journal_entry (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sales_invoice
            ADD
              CONSTRAINT fk_sales_invoice_tercero_id FOREIGN KEY (tercero_id) REFERENCES tercero (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sales_invoice_line
            ADD
              CONSTRAINT FK_4F9CDB2BC33F7837 FOREIGN KEY (document_id) REFERENCES sales_invoice (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sales_invoice_line
            ADD
              CONSTRAINT fk_sales_invoice_line_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sales_invoice_line
            ADD
              CONSTRAINT fk_sales_invoice_line_product_id FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sales_invoice_line
            ADD
              CONSTRAINT fk_sales_invoice_line_account_id FOREIGN KEY (account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sales_invoice_payment
            ADD
              CONSTRAINT FK_E97F5E8A2989F1FD FOREIGN KEY (invoice_id) REFERENCES sales_invoice (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sales_invoice_payment
            ADD
              CONSTRAINT fk_sales_invoice_payment_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sales_invoice_payment
            ADD
              CONSTRAINT fk_sales_invoice_payment_payment_method_id FOREIGN KEY (payment_method_id) REFERENCES payment_method (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              supplier_payment
            ADD
              CONSTRAINT fk_supplier_payment_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              supplier_payment
            ADD
              CONSTRAINT fk_supplier_payment_tercero_id FOREIGN KEY (tercero_id) REFERENCES tercero (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              supplier_payment
            ADD
              CONSTRAINT fk_supplier_payment_payment_method_id FOREIGN KEY (payment_method_id) REFERENCES payment_method (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              supplier_payment_allocation
            ADD
              CONSTRAINT FK_227BF6BF4C3A3BB FOREIGN KEY (payment_id) REFERENCES supplier_payment (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              supplier_payment_allocation
            ADD
              CONSTRAINT fk_supplier_payment_allocation_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tax
            ADD
              CONSTRAINT fk_tax_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tax
            ADD
              CONSTRAINT fk_tax_sales_account_id FOREIGN KEY (sales_account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tax
            ADD
              CONSTRAINT fk_tax_purchase_account_id FOREIGN KEY (purchase_account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tercero
            ADD
              CONSTRAINT fk_tercero_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tercero
            ADD
              CONSTRAINT fk_tercero_receivable_account_id FOREIGN KEY (receivable_account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tercero
            ADD
              CONSTRAINT fk_tercero_payable_account_id FOREIGN KEY (payable_account_id) REFERENCES ledger_account (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tercero_contact
            ADD
              CONSTRAINT FK_DB04D3DD7F999FDB FOREIGN KEY (tercero_id) REFERENCES tercero (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tercero_contact
            ADD
              CONSTRAINT fk_tercero_contact_company_id FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE access_token DROP FOREIGN KEY fk_access_token_user_id');
        $this->addSql('ALTER TABLE app_user DROP FOREIGN KEY fk_app_user_company_id');
        $this->addSql('ALTER TABLE attachment DROP FOREIGN KEY fk_attachment_company_id');
        $this->addSql('ALTER TABLE audit_log DROP FOREIGN KEY fk_audit_log_company_id');
        $this->addSql('ALTER TABLE cash_receipt DROP FOREIGN KEY fk_cash_receipt_company_id');
        $this->addSql('ALTER TABLE cash_receipt DROP FOREIGN KEY fk_cash_receipt_tercero_id');
        $this->addSql('ALTER TABLE cash_receipt DROP FOREIGN KEY fk_cash_receipt_payment_method_id');
        $this->addSql('ALTER TABLE cash_receipt_allocation DROP FOREIGN KEY FK_6B3610FC2B5CA896');
        $this->addSql('ALTER TABLE cash_receipt_allocation DROP FOREIGN KEY fk_cash_receipt_allocation_company_id');
        $this->addSql('ALTER TABLE invoicing_resolution DROP FOREIGN KEY fk_invoicing_resolution_company_id');
        $this->addSql('ALTER TABLE journal_entry DROP FOREIGN KEY fk_journal_entry_company_id');
        $this->addSql('ALTER TABLE journal_line DROP FOREIGN KEY FK_692C8C7EBA364942');
        $this->addSql('ALTER TABLE journal_line DROP FOREIGN KEY fk_journal_line_company_id');
        $this->addSql('ALTER TABLE journal_line DROP FOREIGN KEY fk_journal_line_account_id');
        $this->addSql('ALTER TABLE journal_line DROP FOREIGN KEY fk_journal_line_tercero_id');
        $this->addSql('ALTER TABLE ledger_account DROP FOREIGN KEY fk_ledger_account_company_id');
        $this->addSql('ALTER TABLE ledger_settings DROP FOREIGN KEY fk_ledger_settings_company_id');
        $this->addSql('ALTER TABLE numbering_series DROP FOREIGN KEY fk_numbering_series_company_id');
        $this->addSql('ALTER TABLE payable DROP FOREIGN KEY fk_payable_company_id');
        $this->addSql('ALTER TABLE payable DROP FOREIGN KEY fk_payable_tercero_id');
        $this->addSql('ALTER TABLE payment_method DROP FOREIGN KEY fk_payment_method_company_id');
        $this->addSql('ALTER TABLE payment_method DROP FOREIGN KEY fk_payment_method_account_id');
        $this->addSql('ALTER TABLE posting_rule DROP FOREIGN KEY fk_posting_rule_company_id');
        $this->addSql('ALTER TABLE posting_rule DROP FOREIGN KEY fk_posting_rule_account_id');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY fk_product_company_id');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY fk_product_category_id');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY fk_product_charge_tax_id');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY fk_product_withholding_tax_id');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY fk_product_revenue_account_id');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY fk_product_expense_account_id');
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY fk_product_category_company_id');
        $this->addSql('ALTER TABLE purchase_invoice DROP FOREIGN KEY fk_purchase_invoice_company_id');
        $this->addSql('ALTER TABLE purchase_invoice DROP FOREIGN KEY fk_purchase_invoice_journal_entry_id');
        $this->addSql('ALTER TABLE purchase_invoice DROP FOREIGN KEY fk_purchase_invoice_tercero_id');
        $this->addSql('ALTER TABLE purchase_invoice_line DROP FOREIGN KEY FK_17CB2133C33F7837');
        $this->addSql('ALTER TABLE purchase_invoice_line DROP FOREIGN KEY fk_purchase_invoice_line_company_id');
        $this->addSql('ALTER TABLE purchase_invoice_line DROP FOREIGN KEY fk_purchase_invoice_line_product_id');
        $this->addSql('ALTER TABLE purchase_invoice_line DROP FOREIGN KEY fk_purchase_invoice_line_account_id');
        $this->addSql('ALTER TABLE purchase_invoice_payment DROP FOREIGN KEY FK_E016617A2989F1FD');
        $this->addSql('ALTER TABLE purchase_invoice_payment DROP FOREIGN KEY fk_purchase_invoice_payment_company_id');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              purchase_invoice_payment
            DROP
              FOREIGN KEY fk_purchase_invoice_payment_payment_method_id
        SQL);
        $this->addSql('ALTER TABLE quotation DROP FOREIGN KEY fk_quotation_company_id');
        $this->addSql('ALTER TABLE quotation DROP FOREIGN KEY fk_quotation_tercero_id');
        $this->addSql('ALTER TABLE quotation_line DROP FOREIGN KEY FK_4CE011BAC33F7837');
        $this->addSql('ALTER TABLE quotation_line DROP FOREIGN KEY fk_quotation_line_company_id');
        $this->addSql('ALTER TABLE quotation_line DROP FOREIGN KEY fk_quotation_line_product_id');
        $this->addSql('ALTER TABLE quotation_line DROP FOREIGN KEY fk_quotation_line_account_id');
        $this->addSql('ALTER TABLE receivable DROP FOREIGN KEY fk_receivable_company_id');
        $this->addSql('ALTER TABLE receivable DROP FOREIGN KEY fk_receivable_tercero_id');
        $this->addSql('ALTER TABLE sales_invoice DROP FOREIGN KEY fk_sales_invoice_company_id');
        $this->addSql('ALTER TABLE sales_invoice DROP FOREIGN KEY fk_sales_invoice_resolution_id');
        $this->addSql('ALTER TABLE sales_invoice DROP FOREIGN KEY fk_sales_invoice_journal_entry_id');
        $this->addSql('ALTER TABLE sales_invoice DROP FOREIGN KEY fk_sales_invoice_tercero_id');
        $this->addSql('ALTER TABLE sales_invoice_line DROP FOREIGN KEY FK_4F9CDB2BC33F7837');
        $this->addSql('ALTER TABLE sales_invoice_line DROP FOREIGN KEY fk_sales_invoice_line_company_id');
        $this->addSql('ALTER TABLE sales_invoice_line DROP FOREIGN KEY fk_sales_invoice_line_product_id');
        $this->addSql('ALTER TABLE sales_invoice_line DROP FOREIGN KEY fk_sales_invoice_line_account_id');
        $this->addSql('ALTER TABLE sales_invoice_payment DROP FOREIGN KEY FK_E97F5E8A2989F1FD');
        $this->addSql('ALTER TABLE sales_invoice_payment DROP FOREIGN KEY fk_sales_invoice_payment_company_id');
        $this->addSql('ALTER TABLE sales_invoice_payment DROP FOREIGN KEY fk_sales_invoice_payment_payment_method_id');
        $this->addSql('ALTER TABLE supplier_payment DROP FOREIGN KEY fk_supplier_payment_company_id');
        $this->addSql('ALTER TABLE supplier_payment DROP FOREIGN KEY fk_supplier_payment_tercero_id');
        $this->addSql('ALTER TABLE supplier_payment DROP FOREIGN KEY fk_supplier_payment_payment_method_id');
        $this->addSql('ALTER TABLE supplier_payment_allocation DROP FOREIGN KEY FK_227BF6BF4C3A3BB');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              supplier_payment_allocation
            DROP
              FOREIGN KEY fk_supplier_payment_allocation_company_id
        SQL);
        $this->addSql('ALTER TABLE tax DROP FOREIGN KEY fk_tax_company_id');
        $this->addSql('ALTER TABLE tax DROP FOREIGN KEY fk_tax_sales_account_id');
        $this->addSql('ALTER TABLE tax DROP FOREIGN KEY fk_tax_purchase_account_id');
        $this->addSql('ALTER TABLE tercero DROP FOREIGN KEY fk_tercero_company_id');
        $this->addSql('ALTER TABLE tercero DROP FOREIGN KEY fk_tercero_receivable_account_id');
        $this->addSql('ALTER TABLE tercero DROP FOREIGN KEY fk_tercero_payable_account_id');
        $this->addSql('ALTER TABLE tercero_contact DROP FOREIGN KEY FK_DB04D3DD7F999FDB');
        $this->addSql('ALTER TABLE tercero_contact DROP FOREIGN KEY fk_tercero_contact_company_id');
        $this->addSql('DROP TABLE access_token');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE attachment');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE cash_receipt');
        $this->addSql('DROP TABLE cash_receipt_allocation');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE invoicing_resolution');
        $this->addSql('DROP TABLE journal_entry');
        $this->addSql('DROP TABLE journal_line');
        $this->addSql('DROP TABLE ledger_account');
        $this->addSql('DROP TABLE ledger_settings');
        $this->addSql('DROP TABLE numbering_series');
        $this->addSql('DROP TABLE payable');
        $this->addSql('DROP TABLE payment_method');
        $this->addSql('DROP TABLE posting_rule');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_category');
        $this->addSql('DROP TABLE purchase_invoice');
        $this->addSql('DROP TABLE purchase_invoice_line');
        $this->addSql('DROP TABLE purchase_invoice_payment');
        $this->addSql('DROP TABLE quotation');
        $this->addSql('DROP TABLE quotation_line');
        $this->addSql('DROP TABLE receivable');
        $this->addSql('DROP TABLE sales_invoice');
        $this->addSql('DROP TABLE sales_invoice_line');
        $this->addSql('DROP TABLE sales_invoice_payment');
        $this->addSql('DROP TABLE supplier_payment');
        $this->addSql('DROP TABLE supplier_payment_allocation');
        $this->addSql('DROP TABLE tax');
        $this->addSql('DROP TABLE tercero');
        $this->addSql('DROP TABLE tercero_contact');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
