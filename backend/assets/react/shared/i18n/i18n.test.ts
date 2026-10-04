import {translator} from './i18n';

describe('translator', () => {
  const t = translator();

  it('finds nested keys and fills placeholders', () => {
    expect(t('common.pageOf', {page: 2, pages: 5})).toBe('Página 2 de 5');
  });

  it('shows a missing key instead of nothing', () => {
    expect(t('nope.missing')).toBe('nope.missing');
  });
});
