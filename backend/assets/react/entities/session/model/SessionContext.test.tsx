import {act, render, screen} from '@testing-library/react';
import {SessionProvider, useSession} from './SessionContext';

const ANA = {
  user_id: 'u1',
  email: 'ana@acme.co',
  name: 'Ana',
  role: 'owner',
  company_id: 'c1',
  company_name: 'Acme',
  company_nit: '900123456',
  company_check_digit: '8',
};

function Probe() {
  const {status, session, replace} = useSession();
  return (
    <>
      <p>{`${status} ${session?.email ?? '-'}`}</p>
      <button type="button" onClick={() => replace(ANA)}>
        sign in
      </button>
    </>
  );
}

describe('the session', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('is not undone by a slower answer asked for before signing in', async () => {
    let answer: (r: Response) => void = () => undefined;
    vi.stubGlobal(
      'fetch',
      vi.fn(() => new Promise<Response>((resolve) => (answer = resolve))),
    );
    render(
      <SessionProvider>
        <Probe />
      </SessionProvider>,
    );

    act(() => screen.getByRole('button').click());
    expect(screen.getByText('signed-in ana@acme.co')).toBeInTheDocument();

    await act(async () =>
      answer(new Response('{"error":"unauthorized"}', {status: 401})),
    );

    expect(screen.getByText('signed-in ana@acme.co')).toBeInTheDocument();
  });
});
