import { test, expect } from './fixtures.mjs';
import { baseURL, ignoreHTTPSErrors } from './helpers/environment.mjs';
import { selectReference } from './helpers/journeys.mjs';

test.use({ timeLogAdmin: true, timeLogSecurity: true });

async function withPersona(browser, personas, persona, use) {
  const context = await browser.newContext({
    baseURL, ignoreHTTPSErrors,
    storageState: persona === 'anonymous' ? { cookies: [], origins: [] } : personas.states[persona],
  });
  try { await use(context); } finally { await context.close(); }
}

test('anonymous cannot read statistics or access TimeLog mutation forms', async ({ page, scenario }) => {
  for (const path of [
    '/time-log/add', '/admin/content/time-log',
    `/time-log/${scenario.timeLog}`, `/time-log/${scenario.timeLog}/edit`,
    `/time-log/${scenario.timeLog}/delete`, `/task/${scenario.tasks.backlog.id}/log-time`,
    `/drupaljira/project-stats/${scenario.projects.kanban}`,
  ]) {
    expect((await page.request.get(path)).status(), path).toBe(403);
  }
  await page.goto(`/node/${scenario.tasks.backlog.id}`);
  await expect(page.getByRole('article')).not.toContainText('written off');
});

test('owner can edit and delete their log; another authenticated user cannot', async ({ browser, personas, scenario }) => {
  await withPersona(browser, personas, 'manager', async context => {
    for (const suffix of ['edit', 'delete']) {
      const path = `/time-log/${scenario.timeLog}/${suffix}`;
      expect((await context.request.get(path)).status()).toBe(403);
      expect((await context.request.post(path, { form: { hours: '999', op: 'Save' } })).status()).toBe(403);
    }
  });
  await withPersona(browser, personas, 'regular', async context => {
    expect((await context.request.get('/admin/content/time-log')).status()).toBe(403);
    const page = await context.newPage();
    expect((await page.goto(`/time-log/${scenario.timeLog}/edit`)).status()).toBe(200);
    await expect(page.getByLabel('User', { exact: true })).toHaveCount(0);
    await page.getByLabel('Hours', { exact: true }).fill('3.25');
    // A forged owner value must not transfer ownership of the record.
    await page.locator('form').filter({ has: page.getByLabel('Hours', { exact: true }) }).evaluate((form, uid) => {
      const input = document.createElement('input');
      input.type = 'hidden'; input.name = 'uid[0][target_id]'; input.value = uid;
      form.append(input);
    }, String(personas.accounts.manager.id));
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/node/${scenario.tasks.backlog.id}$`));
    await expect(page.getByRole('article')).toContainText('3.25 ч. written off');
    expect((await page.goto(`/time-log/${scenario.timeLog}/edit`)).status()).toBe(200);
    expect((await page.goto(`/time-log/${scenario.timeLog}/delete`)).status()).toBe(200);
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/node/${scenario.tasks.backlog.id}$`));
    await expect(page.getByRole('article')).toContainText('0 ч. written off');
    expect((await context.request.get(`/time-log/${scenario.timeLog}/edit`)).status()).toBe(404);
  });
});

test('TimeLog administrator can edit and delete another user’s log', async ({ browser, personas, scenario }) => {
  await withPersona(browser, personas, 'admin', async context => {
    expect((await context.request.get('/admin/content/time-log')).status()).toBe(200);
    const page = await context.newPage();
    expect((await page.goto(`/time-log/${scenario.timeLog}/edit`)).status()).toBe(200);
    await page.getByLabel('Hours', { exact: true }).fill('4.50');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/content\/time-log$/);
    expect((await page.goto(`/time-log/${scenario.timeLog}/edit`)).status()).toBe(200);
    await expect(page.getByLabel('Hours', { exact: true })).toHaveValue('4.50');
    await page.goto(`/time-log/${scenario.timeLog}/delete`);
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/content\/time-log$/);
    expect((await context.request.get(`/time-log/${scenario.timeLog}/edit`)).status()).toBe(404);
  });
});

test('generic add form rejects an inaccessible Project and preserves the logged-in owner', async ({ browser, personas, scenario }) => {
  const note = `Scoped creation ${scenario.namespace}`;
  await withPersona(browser, personas, 'regular', async context => {
    const page = await context.newPage();
    expect((await page.goto('/time-log/add')).status()).toBe(200);
    await expect(page.getByLabel('User', { exact: true })).toHaveCount(0);
    await page.getByLabel('Task', { exact: true }).fill(`E2E taskInPrivateProject ${scenario.namespace} (${scenario.security.taskInPrivateProject})`);
    await page.getByLabel('Hours', { exact: true }).fill('1.25');
    await page.getByLabel('Notes', { exact: true }).fill(note);
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(/\/time-log\/add$/);
    await expect(page.getByText('Select a task and project you can access.', { exact: false })).toBeVisible();
    await selectReference(page, 'Task', scenario.tasks.backlog.title);
    await page.locator('form').filter({ has: page.getByLabel('Task', { exact: true }) }).evaluate((form, uid) => {
      const input = document.createElement('input');
      input.type = 'hidden'; input.name = 'uid[0][target_id]'; input.value = uid;
      form.append(input);
    }, String(personas.accounts.manager.id));
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/node/${scenario.tasks.backlog.id}$`));
    await expect(page.getByRole('article')).toContainText('3.25 ч. written off');
  });
  await withPersona(browser, personas, 'admin', async context => {
    const page = await context.newPage();
    await page.goto('/admin/content/time-log');
    const lastPage = page.getByRole('link', { name: 'Last page', exact: true });
    if (await lastPage.isVisible()) await lastPage.press('Enter');
    const row = page.getByRole('row').filter({ hasText: note });
    await expect(row).toContainText(personas.accounts.regular.name);
    await expect(row).not.toContainText(personas.accounts.manager.name);
  });
});

test('Task and Project access are enforced for logging, records, and statistics', async ({ browser, personas, scenario }) => {
  await withPersona(browser, personas, 'regular', async context => {
    const security = scenario.security;
    for (const task of [security.privateTask, security.taskInPrivateProject]) {
      expect((await context.request.get(`/task/${task}/log-time`)).status()).toBe(403);
      expect((await context.request.post(`/task/${task}/log-time`, { form: { hours: '1', op: 'Log time' } })).status()).toBe(403);
    }
    for (const log of [security.privateTaskLog, security.taskInPrivateProjectLog]) {
      for (const suffix of ['', '/edit', '/delete']) {
        expect((await context.request.get(`/time-log/${log}${suffix}`)).status()).toBe(403);
      }
    }
    expect((await context.request.get(`/drupaljira/project-stats/${security.privateProject}`)).status()).toBe(403);
    expect((await context.request.get(`/drupaljira/project-stats/${scenario.tasks.backlog.id}`)).status()).toBe(404);
    expect((await context.request.get('/drupaljira/project-stats/2147483647')).status()).toBe(404);
    expect((await context.request.get(`/task/${scenario.projects.kanban}/log-time`)).status()).toBe(404);
    const page = await context.newPage();
    await page.goto(`/node/${scenario.projects.kanban}`);
    await expect(page.locator('#project-statistics-wrapper')).toContainText('Total logged: 2 ч.');
    await expect(page.locator('#project-statistics-wrapper')).toContainText('Completed tasks: 1 of 4');
    // Exercise the actual AJAX refresh, preserving the same visibility policy.
    const refreshed = page.waitForResponse(response => new URL(response.url()).pathname === `/drupaljira/project-stats/${scenario.projects.kanban}`);
    await page.getByRole('link', { name: 'Refresh statistics', exact: true }).click();
    expect((await refreshed).ok()).toBeTruthy();
    await expect(page.locator('#project-statistics-wrapper')).toContainText('Total logged: 2 ч.');
    await expect(page.locator('#project-statistics-wrapper')).not.toContainText('42 ч.');
    await page.goto(`/task/${scenario.tasks.backlog.id}/log-time`);
    await expect(page.getByRole('button', { name: 'Log time', exact: true })).toBeVisible();
  });
  await withPersona(browser, personas, 'admin', async context => {
    expect((await context.request.get(`/task/${scenario.security.privateTask}/log-time`)).status()).toBe(200);
    expect((await context.request.get(`/drupaljira/project-stats/${scenario.security.privateProject}`)).status()).toBe(200);
    const page = await context.newPage();
    await page.goto(`/node/${scenario.projects.kanban}`);
    await expect(page.locator('#project-statistics-wrapper')).toContainText('Total logged: 44 ч.');
    await expect(page.locator('#project-statistics-wrapper')).toContainText('Completed tasks: 1 of 5');
  });
  // Revisit after an administrative render to detect cross-account cache leaks.
  await withPersona(browser, personas, 'regular', async context => {
    const page = await context.newPage();
    await page.goto(`/node/${scenario.projects.kanban}`);
    await expect(page.locator('#project-statistics-wrapper')).toContainText('Total logged: 2 ч.');
    await expect(page.locator('#project-statistics-wrapper')).toContainText('Completed tasks: 1 of 4');
  });
  await withPersona(browser, personas, 'anonymous', async context => {
    const page = await context.newPage();
    await page.goto(`/node/${scenario.projects.kanban}`);
    await expect(page.locator('#project-statistics-wrapper')).toHaveCount(0);
  });
});

test('debug endpoints are absent for anonymous, regular, and administrative accounts', async ({ browser, personas, scenario }) => {
  for (const persona of ['anonymous', 'regular', 'admin']) {
    await withPersona(browser, personas, persona, async context => {
      for (const operation of ['crud', 'list', 'sum']) {
        const path = `/admin/drupaljira/timelog-debug/${operation}/${scenario.tasks.backlog.id}`;
        expect((await context.request.get(path)).status(), `${persona} GET ${operation}`).toBe(404);
        expect((await context.request.post(path)).status(), `${persona} POST ${operation}`).toBe(404);
      }
    });
  }
});
