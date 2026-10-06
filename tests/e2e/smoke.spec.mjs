import { test, expect } from './fixtures.mjs';

test('anonymous board shows fixture tasks and opens a modal', async ({ page, scenario }) => {
  await page.goto(`/project/${scenario.projects.kanban}/board`);
  for (const [state, title] of Object.entries({ backlog: 'Backlog', in_progress: 'In Progress', review: 'Review', done: 'Done' })) {
    const column = page.locator(`[data-status="${state}"]`);
    await expect(column.getByRole('heading', { name: title, exact: true })).toBeVisible();
    await expect(column.locator(`[data-task-id="${scenario.tasks[state].id}"]`)).toContainText(scenario.tasks[state].title);
  }
  await page.locator(`[data-task-id="${scenario.tasks.backlog.id}"]`).press('Enter');
  await expect(page.getByRole('dialog')).toContainText('Deterministic E2E task.');
});

async function approve(page, scenario) {
  await page.goto(`/node/${scenario.tasks.review.id}`);
  const token = await page.request.get('/session/token');
  expect(token.ok()).toBeTruthy();
  return page.request.post(`/task/${scenario.tasks.review.id}/status`, {
    headers: { 'X-CSRF-Token': await token.text() },
    data: { status: 'done' },
  });
}

test.describe('regular persona', () => {
  test.use({ persona: 'regular' });
  test('regular user cannot approve tasks', async ({ page, scenario }) => {
    const response = await approve(page, scenario);
    expect(response.status()).toBe(400);
    expect(await response.json()).toHaveProperty('error');
    await page.goto(`/project/${scenario.projects.kanban}/board`);
    await expect(page.locator(`[data-status="review"] [data-task-id="${scenario.tasks.review.id}"]`)).toBeVisible();
  });
});

test.describe('manager persona', () => {
  test.use({ persona: 'manager' });
  test('project manager can approve tasks', async ({ page, scenario }) => {
    const response = await approve(page, scenario);
    expect(response.ok()).toBeTruthy();
    expect(await response.json()).toMatchObject({ success: true, moderation_state: 'done' });
    await page.goto(`/project/${scenario.projects.kanban}/board`);
    await expect(page.locator(`[data-status="done"] [data-task-id="${scenario.tasks.review.id}"]`)).toBeVisible();
  });
});

test('task displays deterministic time summary', async ({ page, scenario }) => {
  await page.goto(`/node/${scenario.tasks.backlog.id}`);
  await expect(page.getByRole('article').getByText('8 ч. (2 ч. written off, 6 ч. remaining)', { exact: true })).toBeVisible();
});

test('sprints are available only for Scrum projects', async ({ page, scenario }) => {
  const denied = await page.goto(`/project/${scenario.projects.kanban}/sprints`);
  expect(denied.status()).toBe(403);
  const allowed = await page.goto(`/project/${scenario.projects.scrum}/sprints`);
  expect(allowed.status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Sprints', exact: true })).toBeVisible();
});
