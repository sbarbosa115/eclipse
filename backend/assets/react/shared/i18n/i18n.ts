import {es} from './locales/es';

// Mustang speaks Spanish (Colombia) only (roadmap principle 6), but every visible string still goes through t(): the
// copy lives in one place and a missing key shows itself.
export type Locale = 'es';
type Tree = {[key: string]: string | Tree};
export type Params = Record<string, string | number>;
export type Translate = (key: string, params?: Params) => string;

const CATALOG: Tree = es;

/**
 * Looks `a.b.c` up in the catalog and fills {{param}} placeholders. A missing key returns the key itself, so a
 * forgotten string is visible instead of blank.
 */
export function translator(): Translate {
  return (key, params) => {
    let value: string | Tree | undefined = CATALOG;
    for (const part of key.split('.')) {
      value = typeof value === 'object' ? value[part] : undefined;
    }
    if (typeof value !== 'string') return key;
    let text = value;
    for (const [name, param] of Object.entries(params ?? {})) {
      text = text.split(`{{${name}}}`).join(String(param));
    }
    return text;
  };
}
