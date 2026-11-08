import {ApiError} from '@/shared/api';
import {translator} from '@/shared/i18n';
import {purchaseOrderErrorMessage, violationsOf} from './errorMessage';

describe('purchase order errors', () => {
  const t = translator();

  it('says the API error in words', () => {
    expect(
      purchaseOrderErrorMessage(
        new ApiError(409, 'purchase_order_already_converted', '', null),
        t,
      ),
    ).toBe(
      'Esta orden de compra ya se convirtió en factura: solo se convierte una vez.',
    );
  });

  it('falls back to something kind for a code it does not know', () => {
    expect(
      purchaseOrderErrorMessage(new ApiError(500, 'weird', '', null), t),
    ).toBe('Algo salió mal. Inténtalo de nuevo.');
  });

  it('reads the violations of a refused request', () => {
    const body = {
      violations: [
        {field: 'tercero_id', message: 'Este tercero está inactivo.'},
      ],
    };

    expect(
      violationsOf(new ApiError(422, 'validation_failed', '', body)),
    ).toEqual(body.violations);
    expect(
      violationsOf(new ApiError(409, 'document_not_draft', '', null)),
    ).toEqual([]);
  });
});
