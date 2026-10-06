import { test, expect } from './fixtures.mjs';
import { createProject, createTask, card, column, expectInColumn } from './helpers/journeys.mjs';

test.use({ persona: 'manager', journeyPermissions: true });
test('UI-created Task appears in its Project Backlog and opens from the board', async ({ page, scenario, personas }) => {
  const project = await createProject(page, scenario);
  const task = await createTask(page, scenario, personas, project);
  await page.goto(`/project/${project.id}/board`);
  await expectInColumn(page, task, 'backlog');
  await card(page, task).getByRole('link', { name: task.title, exact: true }).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByRole('link', { name: task.title, exact: true })).toBeVisible();
  await expect(dialog).toContainText('UI journey description.');
  await expect(dialog.getByRole('link', { name: project.title, exact: true })).toBeVisible();
  await page.goto(`/project/${scenario.projects.kanban}/board`);
  await expect(page.getByText(task.title, { exact: true })).toHaveCount(0);
});

test('Board drag and drop persists Backlog → In Progress → Review → Done → reopen', async ({ page, scenario, personas }) => {
  const project = await createProject(page, scenario);
  const task = await createTask(page, scenario, personas, project);
  await page.goto(`/project/${project.id}/board`);
  await expectInColumn(page, task, 'backlog');
  for (const state of ['in_progress', 'review', 'done', 'in_progress']) {
    // Observe completion of the board's own request; never invoke the endpoint.
    const saved = page.waitForResponse(response => new URL(response.url()).pathname === `/task/${task.id}/status` && response.request().method() === 'POST');
    await card(page, task).dragTo(column(page, state).getByRole('heading'));
    expect((await saved).ok()).toBeTruthy();
    await expectInColumn(page, task, state);
    await page.reload();
    await expectInColumn(page, task, state);
  }
});
