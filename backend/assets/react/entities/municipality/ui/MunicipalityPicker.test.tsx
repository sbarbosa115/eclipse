import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {useState} from 'react';
import {fakeApi} from '@/shared/test/fakeApi';
import {MEDELLIN} from '@/shared/test/municipalities';
import {
  MunicipalityPicker,
  type PickedMunicipality,
} from './MunicipalityPicker';

const BOGOTA = {
  code: '11001',
  name: 'BOGOTÁ, D.C.',
  department_code: '11',
  department_name: 'BOGOTÁ, D.C.',
};

const LABELS = {
  placeholder: 'Departamento, municipio o código',
  minChars: (count: number) => `Escribe al menos ${count} caracteres.`,
  searching: 'Buscando…',
  none: 'Ningún municipio coincide.',
  failed: 'No pudimos buscar municipios.',
};

function Harness({initial = null}: {initial?: PickedMunicipality | null}) {
  const [value, setValue] = useState<PickedMunicipality | null>(initial);
  return (
    <>
      <MunicipalityPicker
        aria-label="Municipio"
        value={value}
        onChange={setValue}
        labels={LABELS}
      />
      <output>{value?.code ?? 'ninguno'}</output>
    </>
  );
}

describe('the municipality picker', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('searches from the second character and shows "Departamento › Municipio (código)"', async () => {
    const api = fakeApi({
      'GET /geo/municipalities': [200, {items: [MEDELLIN]}],
    });
    render(<Harness />);

    const box = screen.getByRole('combobox', {name: 'Municipio'});
    await userEvent.type(box, 'm');
    expect(screen.getByText('Escribe al menos 2 caracteres.')).toBeVisible();
    expect(api.calls).toHaveLength(0);
    await userEvent.type(box, 'e');
    await userEvent.click(
      await screen.findByRole('option', {name: 'Antioquia › Medellín (05001)'}),
    );

    expect(api.calls[0]?.url.searchParams.get('q')).toBe('me');
    expect(screen.getByRole('combobox')).toHaveValue(
      'Antioquia › Medellín (05001)',
    );
    expect(screen.getByText('05001', {selector: 'output'})).toBeInTheDocument();
  });

  it('finds a municipality by its code and keeps the capital D.C.', async () => {
    const api = fakeApi({'GET /geo/municipalities': [200, {items: [BOGOTA]}]});
    render(<Harness />);

    await userEvent.type(screen.getByRole('combobox'), '11001');

    expect(
      await screen.findByRole('option', {
        name: 'Bogotá, D.C. › Bogotá, D.C. (11001)',
      }),
    ).toBeInTheDocument();
    expect(api.calls[0]?.url.searchParams.get('q')).toBe('11001');
  });

  it('drops the choice when the person types over it', async () => {
    fakeApi({'GET /geo/municipalities': [200, {items: []}]});
    render(
      <Harness
        initial={{code: '05001', label: 'Antioquia › Medellín (05001)'}}
      />,
    );

    expect(screen.getByRole('combobox')).toHaveValue(
      'Antioquia › Medellín (05001)',
    );
    await userEvent.type(screen.getByRole('combobox'), 'x');

    expect(
      screen.getByText('ninguno', {selector: 'output'}),
    ).toBeInTheDocument();
  });

  it('says so when nothing matches or the search fails', async () => {
    fakeApi({'GET /geo/municipalities': [200, {items: []}]});
    const first = render(<Harness />);
    await userEvent.type(screen.getByRole('combobox'), 'zzz');
    expect(await screen.findByText('Ningún municipio coincide.')).toBeVisible();
    first.unmount();

    fakeApi({'GET /geo/municipalities': [500, {error: 'boom'}]});
    render(<Harness />);
    await userEvent.type(screen.getByRole('combobox'), 'zzz');
    expect(
      await screen.findByText('No pudimos buscar municipios.'),
    ).toBeVisible();
  });
});
