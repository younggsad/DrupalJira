import { test as base, expect } from '@playwright/test';
import { randomBytes, randomUUID } from 'node:crypto';
import { chmod, mkdir, rm } from 'node:fs/promises';
import { join } from 'node:path';
import { drupal } from './helpers/drupal.mjs';
import { loginWithDiagnostics } from './helpers/auth.mjs';
import { ignoreHTTPSErrors } from './helpers/environment.mjs';

export const test = base.extend({
  fixtureMedia: [false, { option: true }],
  journeyPermissions: [false, { option: true, scope: 'worker' }],
  timeLogAdmin: [false, { option: true, scope: 'worker' }],
  timeLogSecurity: [false, { option: true }],
  persona: ['anonymous', { option: true }],
  storageState: async ({ personas, persona }, use) => {
    await use(persona === 'anonymous' ? { cookies: [], origins: [] } : personas.states[persona]);
  },
  personas: [async ({ browser, journeyPermissions, timeLogAdmin }, use, workerInfo) => {
    const namespace = `worker-${randomUUID()}`;
    const managerPassword = process.env.E2E_MANAGER_PASSWORD || randomBytes(24).toString('hex');
    const userPassword = process.env.E2E_USER_PASSWORD || randomBytes(24).toString('hex');
    const adminPassword = randomBytes(24).toString('hex');
    const directory = `.playwright/auth/${namespace}`;
    await mkdir(directory, { recursive: true, mode: 0o700 });
    try {
      const accounts = await drupal('accounts', namespace, { managerPassword, userPassword, journeyPermissions, timeLogAdmin, adminPassword });
      const states = {};
      const credentials = [['manager', managerPassword], ['regular', userPassword]];
      if (timeLogAdmin) credentials.push(['admin', adminPassword]);
      for (const [persona, password] of credentials) {
        const context = await browser.newContext({ baseURL: workerInfo.project.use.baseURL, ignoreHTTPSErrors });
        try {
          await loginWithDiagnostics(
            context, accounts[persona].name, password,
            join(workerInfo.project.outputDir, 'auth', namespace, persona),
            (name, attachment) => base.info().attach(`auth-${persona}-${name}`, attachment),
          );
          states[persona] = `${directory}/${persona}.json`;
          await context.storageState({ path: states[persona] });
          await chmod(states[persona], 0o600);
        } finally { await context.close(); }
      }
      await use({ namespace, accounts, states });
    } finally {
      try { await drupal('cleanup', namespace); }
      finally { await rm(directory, { recursive: true, force: true }); }
    }
  }, { scope: 'worker', timeout: 180_000 }],
  scenario: async ({ personas, fixtureMedia, timeLogSecurity }, use) => {
    const namespace = `test-${randomUUID()}`;
    try {
      await use(await drupal('scenario', namespace, { users: personas.accounts, media: fixtureMedia, timeLogSecurity }));
    } finally { await drupal('cleanup', namespace); }
  },
});
export { expect };
