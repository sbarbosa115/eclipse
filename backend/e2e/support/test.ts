import {test as base, expect, type Page} from '@playwright/test';

export {expect};

/** The demo company e2e/prepare.sh creates (app:demo:seed). Development password, never used anywhere else. */
export const DEMO = {
  email: 'demo@mustang.test',
  password: 'mustang-demo-123',
  company: 'Comercializadora Demo S.A.S.',
};

/** The password of every company a test signs up. */
export const PASSWORD = 'smoke-password-123';

let addresses = 0;
let companies = 0;

/**
 * A visitor address of its own for every test: the app's rate limits count per IP (the stack trusts
 * X-Forwarded-For during a smoke run), so one test's requests never use up another's allowance.
 */
function nextAddress(): string {
  addresses += 1;
  return `10.77.${Math.floor(addresses / 250) % 250}.${(addresses % 250) + 1}`;
}

/** A NIT nobody used in this run (9 digits, starting at 800…). */
function nextNit(): string {
  companies += 1;
  return String(800_000_000 + (Date.now() % 1_000_000) * 100 + companies);
}

export interface TestCompany {
  page: Page;
  email: string;
  name: string;
  nit: string;
}

interface Fixtures {
  /**
   * Signs up a company of the test's own through the API (its owner is then signed in on the page's context), so
   * each test works on its own data.
   */
  newCompany: (label?: string) => Promise<TestCompany>;
}

export const test = base.extend<Fixtures>({
  // (Playwright wants the first argument destructured, even when nothing of it is used.)
  extraHTTPHeaders: async ({locale: _locale}, provide) => {
    await provide({'X-Forwarded-For': nextAddress()});
  },
  newCompany: async ({page, baseURL}, provide) => {
    await provide(async (label = 'Empresa') => {
      const nit = nextNit();
      const email = `owner-${nit}@mustang.test`;
      const name = `${label} ${nit} S.A.S.`;
      const response = await page.request.post('/api/v1/auth/sign-up', {
        headers: {Origin: new URL(baseURL as string).origin},
        data: {
          company_name: name,
          nit,
          owner_name: 'Dueño de prueba',
          email,
          password: PASSWORD,
        },
      });
      expect(response.status(), `signing ${name} up`).toBe(201);
      return {page, email, name, nit};
    });
  },
});

/**
 * A page's console errors, collected from now on: `expect(errors).toEqual([])` at the end of a view's test. The 401
 * of "who is signed in?" (GET /api/v1/me) before signing in is how the app learns nobody is; Chrome logs it as a
 * failed resource, so that one line is not counted.
 */
export function consoleErrors(page: Page): string[] {
  const errors: string[] = [];
  page.on('console', (message) => {
    const signedOutProbe =
      message.text().includes('status of 401') &&
      message.location().url.endsWith('/api/v1/me');
    if (message.type() === 'error' && !signedOutProbe) {
      errors.push(message.text());
    }
  });
  page.on('pageerror', (error) => errors.push(error.message));
  return errors;
}
