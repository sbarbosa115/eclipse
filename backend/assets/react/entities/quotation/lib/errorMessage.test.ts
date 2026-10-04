import {ApiError} from '@/shared/api';
import {translator} from '@/shared/i18n';
import {quotationErrorMessage, violationsOf} from './errorMessage';

describe('quotation errors', () => {
  const t = translator();

  it('says the API error in words', () => {
    expect(
      quotationErrorMessage(
        new ApiError(409, 'quotation_already_converted', '', null),
        t,
      ),
    ).toBe(
      'Esta cotización ya se convirtió en factura: solo se convierte una vez.',
    );
  });

  it('falls back to something kind for a code it does not know', () => {
    expect(quotationErrorMessage(new ApiError(500, 'weird', '', null), t)).toBe(
      'Algo salió mal. Inténtalo de nuevo.',
    );
  });

  it('reads the violations of a refused request', () => {
    const error = new ApiError(422, 'validation_failed', '', {
      violations: [
        {field: 'lines.0.product_id', message: 'Este producto está inactivo.'},
      ],
    });
    expect(violationsOf(error)).toEqual([
      {field: 'lines.0.product_id', message: 'Este producto está inactivo.'},
    ]);
    expect(
      violationsOf(new ApiError(409, 'quotation_not_open', '', null)),
    ).toEqual([]);
  });
});
