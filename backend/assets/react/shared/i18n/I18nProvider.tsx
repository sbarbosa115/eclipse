import {translator, type Locale, type Translate} from './i18n';

interface I18n {
  locale: Locale;
  t: Translate;
}

const I18N: I18n = {locale: 'es', t: translator()};

/** The UI's strings, in Spanish (Colombia). */
export function useTranslation(): I18n {
  return I18N;
}
