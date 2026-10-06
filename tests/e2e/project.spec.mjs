import { test, expect } from './fixtures.mjs';
import { createProject, saveNode } from './helpers/journeys.mjs';

test.use({ persona: 'manager', journeyPermissions: true });
test('Project defaults to Kanban and an edit to Scrum persists', async ({ page, scenario }) => {
  const project = await createProject(page, scenario);
  await page.reload();
  await expect(page.getByRole('article').getByText('Kanban', { exact: true })).toBeVisible();
  await page.goto(`${project.path}/edit`);
  await expect(page.getByLabel('Project Type', { exact: true })).toHaveValue('kanban');
  await page.getByLabel('Project Type', { exact: true }).selectOption({ label: 'Scrum' });
  await saveNode(page, project.title);
  await page.reload();
  await expect(page.getByRole('article').getByText('Scrum', { exact: true })).toBeVisible();
  await page.goto(`${project.path}/edit`);
  await expect(page.getByLabel('Project Type', { exact: true })).toHaveValue('scrum');
});
