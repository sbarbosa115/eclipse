import {screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/** What GET /geo/municipalities answers for "med" in the tests. */
export const MEDELLIN = {
  code: '05001',
  name: 'MEDELLÍN',
  department_code: '05',
  department_name: 'ANTIOQUIA',
};

/** The answer of `fakeApi` for `GET /geo/municipalities`. */
export const GEO: [number, unknown] = [200, {items: [MEDELLIN]}];

/** Types "med" in the Municipio box and picks Medellín from the list. */
export async function pickMedellin() {
  await userEvent.type(screen.getByLabelText('Municipio'), 'med');
  await userEvent.click(
    await screen.findByRole('option', {name: /Medellín \(05001\)/}),
  );
}
