<?php

namespace App\Sales\Application\Document;

use App\Company\Application\Query\Companies;
use App\Company\Application\Query\LogoReader;
use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Application\SalesCalendar;
use App\Sales\Domain\Model\Quotation;
use App\Sales\Domain\Model\QuotationLine;
use App\Sales\Domain\Model\QuotationStatus;
use App\Sales\Domain\Repository\QuotationRepository;
use App\Shared\Application\Port\PdfRenderer;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The cotización's PDF (§4.7): the company's header and logo, both parties, the fecha de vencimiento of the offer, the
 * encabezado, the lines, the totals with each tax, the condiciones comerciales and the observaciones. A draft prints as
 * BORRADOR, a voided one as ANULADA, a rejected or lapsed one says so. The texts are plain text: Twig escapes them and
 * only line breaks are kept (paragraphs), so nobody's markup reaches the PDF.
 */
final class QuotationPdf
{
    public const TEMPLATE = 'pdf/quotation/quotation.html.twig';

    public function __construct(
        private readonly QuotationRepository $quotations,
        private readonly Companies $companies,
        private readonly LogoReader $logos,
        private readonly TerceroDirectory $terceros,
        private readonly PdfRenderer $renderer,
        private readonly SalesCalendar $calendar,
    ) {
    }

    public function render(Uuid $companyId, Uuid $quotationId): QuotationDocument
    {
        $quotation = $this->quotations->get($companyId, $quotationId);
        $context = $this->context($quotation);
        $number = $quotation->number() ?? 'borrador';

        return new QuotationDocument(
            $this->renderer->render(self::TEMPLATE, $context),
            \sprintf('cotizacion-%s.pdf', preg_replace('/[^A-Za-z0-9-]/', '', $number)),
            $quotation->number() ?? '',
            $context['company']->legalName,
            $quotation->terceroId()->toRfc4122(),
            Printed::money($quotation->netTotal()),
            Printed::date($quotation->expiryDate()),
        );
    }

    /**
     * What the template reads, everything already printed as text.
     *
     * @return array<string, mixed>
     */
    public function context(Quotation $quotation): array
    {
        $companyId = $quotation->companyId();
        $client = $this->terceros->get($companyId, $quotation->terceroId());
        $contact = null === $quotation->contactId() ? null : $this->terceros->contactName($companyId, $quotation->terceroId(), $quotation->contactId());
        $responsible = null;
        if (null !== $quotation->responsibleId()) {
            try {
                $responsible = $this->terceros->get($companyId, $quotation->responsibleId())->displayName;
            } catch (NotFound) {
                // Erased since (Ley 1581): the quotation prints without it.
            }
        }
        $status = $quotation->statusOn($this->calendar->today());

        return [
            'company' => $this->companies->view($companyId),
            'logo_data_uri' => $this->logo($companyId),
            'title' => 'Cotización',
            'number' => $quotation->number(),
            'voided' => QuotationStatus::Voided === $status,
            'draft' => QuotationStatus::Draft === $status,
            'banner' => match ($status) {
                QuotationStatus::Voided => 'ANULADA',
                QuotationStatus::Draft => 'BORRADOR',
                default => null,
            },
            'status_note' => match ($status) {
                QuotationStatus::Expired => 'Oferta vencida',
                QuotationStatus::Rejected => 'Cotización rechazada',
                QuotationStatus::Accepted => 'Cotización aceptada',
                default => null,
            },
            'quotation' => [
                'issue_date' => Printed::date($quotation->issueDate()),
                'expiry_date' => Printed::date($quotation->expiryDate()),
                'responsible' => $responsible,
                'header' => self::paragraphs($quotation->header()),
                'terms' => self::paragraphs($quotation->terms()),
                'notes' => self::paragraphs($quotation->notes()),
                'void_reason' => $quotation->voidReason(),
                'voided_at' => Printed::date($quotation->voidedAt()),
            ],
            'client' => [
                'name' => $client->displayName,
                'identification' => self::identification($client->identificationType, $client->identificationNumber, $client->checkDigit),
                'address' => trim(implode(', ', array_filter([$client->address, $client->city]))),
                'email' => $client->email,
                'contact' => $contact,
            ],
            'lines' => array_map(static fn (QuotationLine $l) => [
                'position' => $l->position(),
                'description' => $l->description(),
                'quantity' => Printed::decimal($l->quantity()->toString()),
                'unit_price' => Printed::money(Money::rounded($l->unitPrice()->toBigDecimal())),
                'discount' => $l->discount()->isZero() ? '' : Printed::decimal($l->discount()->toString()).' %',
                'charge_tax' => $l->chargeTax()->isNone() ? '' : $l->chargeTax()->name(),
                'total' => Printed::money($l->subtotalAmount()->plus($l->taxAmount())),
            ], $quotation->lines()),
            'taxes' => self::taxes($quotation, true),
            'withholdings' => self::taxes($quotation, false),
            'totals' => [
                'gross' => Printed::money($quotation->grossTotal()),
                'discounts' => Printed::money($quotation->discountTotal()),
                'subtotal' => Printed::money($quotation->subtotal()),
                'taxes' => Printed::money($quotation->taxTotal()),
                'withholdings' => Printed::money($quotation->withholdingTotal()),
                'net' => Printed::money($quotation->netTotal()),
                'has_discounts' => !$quotation->discountTotal()->isZero(),
                'has_withholdings' => !$quotation->withholdingTotal()->isZero(),
            ],
        ];
    }

    /**
     * Plain text as paragraphs: blocks separated by a blank line, each block's line breaks kept by the template. Nothing
     * is interpreted as markup here or there.
     *
     * @return list<string>
     */
    public static function paragraphs(?string $text): array
    {
        if (null === $text || '' === trim($text)) {
            return [];
        }
        $blocks = preg_split('/\R{2,}/', str_replace("\r\n", "\n", trim($text))) ?: [];

        return array_values(array_filter(array_map('trim', $blocks), static fn (string $b) => '' !== $b));
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

    /**
     * Each impuesto cargo (or retención) with its base and amount, summed by tax.
     *
     * @return list<array{name: string, base: string, amount: string}>
     */
    private static function taxes(Quotation $quotation, bool $charges): array
    {
        $byName = [];
        foreach ($quotation->lines() as $line) {
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
