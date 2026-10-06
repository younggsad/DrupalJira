import { test, expect } from './fixtures.mjs';
import { createProject, createTask, saveNode } from './helpers/journeys.mjs';

test.use({ persona: 'manager', journeyPermissions: true, fixtureMedia: 'pdf' });
test('Media Library attaches an image and PDF that persist on the Task', async ({ page, scenario, personas }) => {
  const project = await createProject(page, scenario);
  const task = await createTask(page, scenario, personas, project);
  await page.goto(`${task.path}/edit`);
  await page.getByRole('button', { name: 'Add media', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible();
  // Activate type switches through the real keyboard UI. The custom theme
  // gives these links overlapping pointer hit areas; do not force a click.
  await dialog.getByRole('button', { name: 'Show Image media', exact: true }).press('Enter');
  await dialog.getByLabel('Name', { exact: true }).fill(`E2E image ${scenario.namespace}`);
  await dialog.getByRole('button', { name: 'Apply filters', exact: true }).click();
  await dialog.getByRole('checkbox', { name: `Select E2E image ${scenario.namespace}`, exact: true }).check();
  await dialog.getByRole('button', { name: 'Show Document media', exact: true }).press('Enter');
  await dialog.getByLabel('Name', { exact: true }).fill(`E2E document ${scenario.namespace}`);
  await dialog.getByRole('button', { name: 'Apply filters', exact: true }).click();
  await dialog.getByRole('checkbox', { name: `Select E2E document ${scenario.namespace}`, exact: true }).check();
  await dialog.getByRole('button', { name: /Insert selected/ }).click();
  await expect(dialog).not.toBeVisible();
  await saveNode(page, task.title);
  await page.reload();
  const article = page.getByRole('article');
  await expect(article.getByAltText('E2E fixture image')).toBeVisible();
  await expect(article.getByRole('link', { name: 'fixture.pdf', exact: true })).toBeVisible();
  await page.goto(`${task.path}/edit`);
  await expect(page.getByText(`E2E image ${scenario.namespace}`, { exact: true })).toBeVisible();
  await expect(page.getByText(`E2E document ${scenario.namespace}`, { exact: true })).toBeVisible();
});
