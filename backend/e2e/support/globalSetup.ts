import {request} from '@playwright/test';
import {DEMO} from './test';

/** Before any spec: the stack answers and has its demo company (e2e/prepare.sh). */
export default async function globalSetup(): Promise<void> {
  const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:8090';
  const api = await request.newContext({baseURL});
  const signIn = await api.post('/api/v1/auth/sign-in', {
    headers: {'X-Forwarded-For': '10.99.0.1'},
    data: {email: DEMO.email, password: DEMO.password},
  });
  if (!signIn.ok()) {
    throw new Error(
      `The stack at ${baseURL} has no demo company (signing in answered ${signIn.status()}). Run backend/e2e/smoke.sh, which prepares it.`,
    );
  }
  await api.dispose();
}
