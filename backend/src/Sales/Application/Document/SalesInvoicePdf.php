<?php

namespace App\Sales\Application\Document;

use App\Company\Application\Query\Companies;
use App\Company\Application\Query\LogoReader;
use App\Company\Application\Query\Resolutions;
use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Domain\Model\SalesInvoice;
use App\Sales\Domain\Model\SalesInvoiceLine;
use App\Sales\Domain\Model\SalesInvoicePayment;
use App\Sales\Domain\Repository\SalesInvoiceRepository;
use App\Shared\Application\Port\PdfRenderer;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Model\InvoiceStatus;
use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The factura de venta's PDF (§4.8): the company's header and logo, the resolution's text, the numbers, both parties'
 * fiscal data, the lines, the totals with each tax, the formas de pago and the observaciones; ANULADA across a voided
 * one (§4.12). A draft prints as BORRADOR, without a number.
 */
final class SalesInvoicePdf
{
    public const TEMPLATE = 'pdf/sales_invoice/invoice.html.twig';

    public function __construct(
        private readonly SalesInvoiceRepository $invoices,
        private readonly Companies $companies,
        private readonly Resolutions $resolutions,
        private readonly LogoReader $logos,
        private readonly TerceroDirectory $terceros,
        private readonly PdfRenderer $renderer,
    ) {
    }

    public function render(Uuid $companyId, Uuid $invoiceId): SalesInvoiceDocument
    {
        $invoice = $this->invoices->get($companyId, $invoiceId);
        $context = $this->context($invoice);
        $number = $invoice->number() ?? 'borrador';

        return new SalesInvoiceDocument(
            $this->renderer->render(self::TEMPLATE, $context),
            \sprintf('factura-%s.pdf', preg_replace('/[^A-Za-z0-9-]/', '', $number)),
            $invoice->number() ?? '',
            $context['company']->legalName,
            $invoice->terceroId()->toRfc4122(),
            Printed::money($invoice->netTotal()),
        );
    }

    /**
     * What the template reads, everything already printed as text.
     *
     * @return array<string, mixed>
     */
    public function context(SalesInvoice $invoice): array
    {
        $companyId = $invoice->companyId();
        $client = $this->terceros->get($companyId, $invoice->terceroId());
        $resolution = $this->resolutions->settings($companyId)->resolution;
        $contact = null === $invoice->contactId() ? null : $this->terceros->contactName($companyId, $invoice->terceroId(), $invoice->contactId());

        return [
            'company' => $this->companies->view($companyId),
            'logo_data_uri' => $this->logo($companyId),
            'title' => 'Factura de venta',
            'number' => $invoice->number(),
            'voided' => InvoiceStatus::Voided === $invoice->status(),
            'draft' => InvoiceStatus::Draft === $invoice->status(),
            'resolution' => null === $resolution ? null : \sprintf(
                'Resolución DIAN No. %s del %s, vigente hasta el %s. Autoriza la numeración del %s-%d al %s-%d. Modalidad %s.',
                $resolution->resolutionNumber,
                Printed::date(new \DateTimeImmutable($resolution->validFrom)),
                Printed::date(new \DateTimeImmutable($resolution->validTo)),
                $resolution->prefix,
                $resolution->rangeFrom,
                $resolution->prefix,
                $resolution->rangeTo,
                'manual' === $resolution->mode ? 'facturación por talonario (manual)' : 'facturación electrónica',
            ),
            'invoice' => [
                'issue_date' => Printed::date($invoice->issueDate()),
                'internal_number' => $invoice->internalNumber(),
                'due_date' => Printed::date(self::lastDueDate($invoice)),
                'notes' => $invoice->notes(),
                'void_reason' => $invoice->voidReason(),
                'voided_at' => Printed::date($invoice->voidedAt()),
            ],
            'client' => [
                'name' => $client->displayName,
                'identification' => self::identification($client->identificationType, $client->identificationNumber, $client->checkDigit),
                'address' => trim(implode(', ', array_filter([$client->address, $client->city]))),
                'email' => $client->email,
                'contact' => $contact,
                'fiscal_responsibilities' => implode(', ', $client->fiscalResponsibilities),
            ],
            'lines' => array_map(static fn (SalesInvoiceLine $l) => [
                'position' => $l->position(),
                'description' => $l->description(),
                'quantity' => Printed::decimal($l->quantity()->toString()),
                'unit_price' => Printed::money(Money::rounded($l->unitPrice()->toBigDecimal())),
                'discount' => $l->discount()->isZero() ? '' : Printed::decimal($l->discount()->toString()).' %',
                'charge_tax' => $l->chargeTax()->isNone() ? '' : $l->chargeTax()->name(),
                'total' => Printed::money($l->subtotalAmount()->plus($l->taxAmount())),
            ], $invoice->lines()),
            'taxes' => self::taxes($invoice, true),
            'withholdings' => self::taxes($invoice, false),
            'totals' => [
                'gross' => Printed::money($invoice->grossTotal()),
                'discounts' => Printed::money($invoice->discountTotal()),
                'subtotal' => Printed::money($invoice->subtotal()),
                'taxes' => Printed::money($invoice->taxTotal()),
                'withholdings' => Printed::money($invoice->withholdingTotal()),
                'net' => Printed::money($invoice->netTotal()),
                'has_discounts' => !$invoice->discountTotal()->isZero(),
                'has_withholdings' => !$invoice->withholdingTotal()->isZero(),
            ],
            'payments' => array_map(static fn (SalesInvoicePayment $p) => [
                'method' => $p->methodName(),
                'kind' => PaymentKind::Credit === $p->kind() ? 'Crédito' : 'Contado',
                'amount' => Printed::money($p->amount()),
                'due_date' => Printed::date($p->dueDate()),
            ], $invoice->payments()),
        ];
    }

    private function logo(Uuid $companyId): ?string
    {
        try {
            $logo = $this->logos->logo($companyId);
        } catch (NotFound) {
            return null;
        }
        $bytes = @file_get_contents($logo->path);

        return false === $bytes ? null : 'data:'.$logo->contentType.';base64,'.base64_encode($bytes);
    }

    private static function identification(string $type, string $number, ?string $checkDigit): string
    {
        $label = ['nit' => 'NIT', 'cc' => 'C.C.', 'ce' => 'C.E.', 'pasaporte' => 'Pasaporte', 'ti' => 'T.I.'][$type] ?? strtoupper($type);

        return $label.' '.$number.(null === $checkDigit || '' === $checkDigit ? '' : '-'.$checkDigit);
    }

    private static function lastDueDate(SalesInvoice $invoice): ?\DateTimeImmutable
    {
        $dates = array_filter(array_map(static fn (SalesInvoicePayment $p) => $p->dueDate(), $invoice->payments()));

        return [] === $dates ? null : max($dates);
    }

    /**
     * Each impuesto cargo (or retención) with its base and amount, summed by tax.
     *
     * @return list<array{name: string, base: string, amount: string}>
     */
    private static function taxes(SalesInvoice $invoice, bool $charges): array
    {
        $byName = [];
        foreach ($invoice->lines() as $line) {
            $tax = $charges ? $line->chargeTax() : $line->withholdingTax();
            $amount = $charges ? $line->taxAmount() : $line->withholdingAmount();
            if ($tax->isNone()) {
                continue;
            }
            $key = self::taxKey($tax);
            $byName[$key] ??= [$tax->name(), Money::zero(), Money::zero()];
            $byName[$key][1] = $byName[$key][1]->plus($line->subtotalAmount());
            $byName[$key][2] = $byName[$key][2]->plus($amount);
        }

        return array_values(array_map(static fn (array $t) => ['name' => $t[0], 'base' => Printed::money($t[1]), 'amount' => Printed::money($t[2])], $byName));
    }

    private static function taxKey(TaxSnapshot $tax): string
    {
        return $tax->name().'|'.$tax->value();
    }
}
