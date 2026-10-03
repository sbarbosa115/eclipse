import {expect, type APIRequestContext} from '@playwright/test';

/** The mail catcher's API (Mailpit). In the e2e container it is http://mailpit:8025. */
const MAILPIT = process.env.MAILPIT_URL ?? 'http://localhost:8025';

export interface CaughtEmail {
  id: string;
  subject: string;
  to: string[];
  text: string;
  html: string;
  /** Every http(s) address in the text part, in order. */
  links: string[];
}

interface MailpitSummary {
  ID: string;
  Subject: string;
  Created: string;
  To: {Address: string}[];
  Bcc?: {Address: string}[];
}

/**
 * The newest email sent to an address since `since` (default: any), waiting for it to arrive: emails go through a
 * queue, so they land a moment after the action.
 */
export async function emailTo(
  api: APIRequestContext,
  address: string,
  options: {subject?: RegExp; since?: Date; timeout?: number} = {},
): Promise<CaughtEmail> {
  let found: MailpitSummary | undefined;
  await expect
    .poll(
      async () => {
        const response = await api.get(`${MAILPIT}/api/v1/search`, {
          params: {query: `to:${address}`, limit: '50'},
        });
        const {messages} = (await response.json()) as {
          messages: MailpitSummary[];
        };
        found = messages.find(
          (m) =>
            (!options.subject || options.subject.test(m.Subject)) &&
            (!options.since || new Date(m.Created) >= options.since),
        );
        return found !== undefined;
      },
      {
        message: `an email to ${address}${options.subject ? ` with subject ${options.subject}` : ''}`,
        timeout: options.timeout ?? 20_000,
      },
    )
    .toBe(true);
  const summary = found as MailpitSummary;
  const full = (await (
    await api.get(`${MAILPIT}/api/v1/message/${summary.ID}`)
  ).json()) as {
    Text: string;
    HTML: string;
  };
  return {
    id: summary.ID,
    subject: summary.Subject,
    to: summary.To.map((t) => t.Address),
    text: full.Text,
    html: full.HTML,
    links: full.Text.match(/https?:\/\/[^\s<>)"']+/g) ?? [],
  };
}

/** How many emails an address has received (to assert that nothing was sent). */
export async function emailCount(
  api: APIRequestContext,
  address: string,
): Promise<number> {
  const response = await api.get(`${MAILPIT}/api/v1/search`, {
    params: {query: `to:${address}`, limit: '200'},
  });
  return ((await response.json()) as {messages: unknown[]}).messages.length;
}
