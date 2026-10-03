import {ApiError} from '@/shared/api';
import {translator} from '@/shared/i18n';
import {terceroErrorMessage, violationsOf} from './errors';

const t = translator();

describe('tercero errors', () => {
  it('names a refusal by its code', () => {
    const error = new ApiError(409, 'tercero_in_use', 'developer text', null);

    expect(terceroErrorMessage(error, t)).toBe(
      'Hay documentos con este tercero: no se puede eliminar, solo desactivar.',
    );
  });

  it('says the role does not allow it on a 403', () => {
    expect(
      terceroErrorMessage(new ApiError(403, 'forbidden', '', null), t),
    ).toBe('Tu rol no permite esta acción.');
  });

  it('falls back to a general message', () => {
    expect(terceroErrorMessage(new Error('boom'), t)).toBe(
      'Algo salió mal. Inténtalo de nuevo.',
    );
  });

  it('reads violations by field, first one wins', () => {
    const error = new ApiError(422, 'duplicate_identification', '', {
      violations: [
        {field: 'identification_number', message: 'Ya existe.'},
        {field: 'identification_number', message: 'Otra.'},
      ],
    });

    expect(violationsOf(error)).toEqual({identification_number: 'Ya existe.'});
    expect(violationsOf(new Error('x'))).toEqual({});
  });
});
