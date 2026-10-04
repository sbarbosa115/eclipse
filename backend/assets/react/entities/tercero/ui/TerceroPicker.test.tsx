import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {useState} from 'react';
import {fakeApi} from '@/shared/test/fakeApi';
import {TerceroPicker, type PickedTercero} from './TerceroPicker';

const ANDINA = {
  id: 't1',
  display_name: 'Distribuciones Andina S.A.S.',
  identification_type: 'nit',
  identification_number: '800197268',
  check_digit: '4',
  branch_code: '0',
};

const LABELS = {
  placeholder: 'Nombre o identificación',
  minChars: (count: number) => `Escribe al menos ${count} caracteres.`,
  searching: 'Buscando…',
  none: 'Ningún cliente coincide.',
  failed: 'No pudimos buscar.',
};

function Harness({
  activeOnly,
  onCreate,
  role,
}: {
  activeOnly?: boolean;
  onCreate?: (text: string) => void;
  role?: 'proveedor';
}) {
  const [value, setValue] = useState<PickedTercero | null>(null);
  return (
    <>
      <TerceroPicker
        aria-label="Cliente"
        value={value}
        onChange={setValue}
        labels={LABELS}
        activeOnly={activeOnly}
        onCreate={onCreate}
        role={role}
      />
      <output>{value?.id ?? 'ninguno'}</output>
      <button type="button" onClick={() => setValue({id: 't9', name: 'Nuevo'})}>
        Elegir desde fuera
      </button>
    </>
  );
}

const page = (items: unknown[]) => ({
  items,
  total: items.length,
  page: 1,
  per_page: 10,
});

describe('the tercero picker', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('searches from the third character, with the role, inactive ones included', async () => {
    const api = fakeApi({'GET /terceros': [200, page([ANDINA])]});
    render(<Harness role="proveedor" />);

    await userEvent.type(screen.getByRole('combobox', {name: 'Cliente'}), 'Di');
    expect(screen.getByText('Escribe al menos 3 caracteres.')).toBeVisible();
    await userEvent.type(screen.getByRole('combobox', {name: 'Cliente'}), 's');
    await userEvent.click(
      await screen.findByRole('option', {name: /Distribuciones Andina/}),
    );

    expect(screen.getByRole('status')).toHaveTextContent('t1');
    const asked = api.calls.at(-1)?.url.searchParams;
    expect(asked?.get('q')).toBe('Dis');
    expect(asked?.get('role')).toBe('proveedor');
    expect(asked?.has('active'), 'a deactivated tercero may still pay').toBe(
      false,
    );
  });

  it('asks only for active terceros, and offers to create one, when the document editor wants it', async () => {
    const api = fakeApi({'GET /terceros': [200, page([])]});
    const onCreate = vi.fn();
    render(<Harness activeOnly onCreate={onCreate} />);

    await userEvent.type(
      screen.getByRole('combobox', {name: 'Cliente'}),
      'Nadie',
    );
    await userEvent.click(
      await screen.findByRole('option', {name: '+ Crear nuevo'}),
    );

    expect(onCreate).toHaveBeenCalledWith('Nadie');
    expect(api.calls.at(-1)?.url.searchParams.get('active')).toBe('1');
  });

  it('shows a tercero chosen from outside (one just created)', async () => {
    fakeApi({});
    render(<Harness />);

    await userEvent.click(
      screen.getByRole('button', {name: 'Elegir desde fuera'}),
    );

    expect(screen.getByRole('combobox', {name: 'Cliente'})).toHaveValue(
      'Nuevo',
    );
  });
});
