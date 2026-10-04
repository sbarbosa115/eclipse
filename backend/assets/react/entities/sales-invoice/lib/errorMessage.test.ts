import {ApiError} from '@/shared/api';
import {translator} from '@/shared/i18n';
import {salesInvoiceErrorMessage} from './errorMessage';

const t = translator();

describe('sales invoice error messages', () => {
  it('says which totals do not match', () => {
    const error = new ApiError(422, 'payments_do_not_match_total', '', {
      error: 'payments_do_not_match_total',
      detail: {payments_total: '1000000.00', net_total: '1190000.00'},
    });

    expect(salesInvoiceErrorMessage(error, t)).toBe(
      'Las formas de pago suman $ 1.000.000,00 y el total neto es $ 1.190.000,00: deben coincidir para emitir.',
    );
  });

  it('names each refusal by its code', () => {
    expect(
      salesInvoiceErrorMessage(
        new ApiError(409, 'document_has_allocations', '', null),
        t,
      ),
    ).toBe('Esta factura tiene recibos de caja aplicados: anúlalos primero.');
  });

  it('falls back to a generic message', () => {
    expect(
      salesInvoiceErrorMessage(new ApiError(500, 'boom', '', null), t),
    ).toBe('Algo salió mal. Inténtalo de nuevo.');
    expect(salesInvoiceErrorMessage(new TypeError('fetch'), t)).toBe(
      'No hay conexión con el servidor. Revisa tu internet e inténtalo de nuevo.',
    );
  });
});
