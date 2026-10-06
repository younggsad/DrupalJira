import { test, expect } from './fixtures.mjs';
import { createProject, createTask, fillTask, saveNode, selectReference, track, expectInColumn } from './helpers/journeys.mjs';

test.use({ persona: 'manager', journeyPermissions: true });
test('Task creation and editing persist Project, Backlog, assignee, decimal estimate and description', async ({ page, scenario, personas }) => {
  const project = await createProject(page, scenario);
  const task = await createTask(page, scenario, personas, project);
  await page.reload();
  const article = page.getByRole('article');
  await expect(article).toContainText(/Status\s*Backlog/);
  await expect(article.getByRole('link', { name: project.title, exact: true })).toBeVisible();
  await expect(article.getByText('UI journey description.', { exact: true })).toBeVisible();
  await expect(article.getByText('3.5 ч.', { exact: false })).toBeVisible();
  await page.goto(`${task.path}/edit`);
  await expect(page.getByLabel('Assignee', { exact: true })).toHaveValue(`${personas.accounts.regular.name} (${personas.accounts.regular.id})`);
  await expect(page.getByLabel('Hours', { exact: true })).toHaveValue('3');
  await expect(page.getByLabel('Minutes', { exact: true })).toHaveValue('30');
  await expect(page.getByLabel('Body', { exact: true })).toHaveValue('UI journey description.');
  await expect(page.getByRole('button', { name: 'Save as In progress', exact: true })).toBeVisible();
  const editedTitle = `E2E UI edited ${scenario.namespace}`;
  await track(scenario, 'task', editedTitle);
  await page.getByLabel('Title', { exact: true }).fill(editedTitle);
  await selectReference(page, 'Assignee', personas.accounts.manager.name);
  await page.getByLabel('Hours', { exact: true }).fill('5');
  await page.getByLabel('Minutes', { exact: true }).fill('15');
  await page.getByLabel('Body', { exact: true }).fill('Edited UI description.');
  await saveNode(page, editedTitle);
  await page.reload();
  await expect(article).toContainText(/Status\s*Backlog/);
  await expect(article.getByText('Edited UI description.', { exact: true })).toBeVisible();
  await expect(article.getByText('5.25 ч.', { exact: false })).toBeVisible();
  await page.goto(`${task.path}/edit`);
  await expect(page.getByRole('button', { name: 'Save as In progress', exact: true })).toBeVisible();
  await expect(page.getByLabel('Title', { exact: true })).toHaveValue(editedTitle);
  await expect(page.getByLabel('Project', { exact: true })).toHaveValue(`${project.title} (${project.id})`);
  await expect(page.getByLabel('Assignee', { exact: true })).toHaveValue(`${personas.accounts.manager.name} (${personas.accounts.manager.id})`);
  await expect(page.getByLabel('Hours', { exact: true })).toHaveValue('5');
  await expect(page.getByLabel('Minutes', { exact: true })).toHaveValue('15');
  await expect(page.getByLabel('Body', { exact: true })).toHaveValue('Edited UI description.');
});

test('Task without required Project shows validation and is not created', async ({ page, scenario, personas }) => {
  const title = `E2E UI invalid ${scenario.namespace}`;
  await track(scenario, 'task', title);
  await page.goto('/node/add/task');
  await fillTask(page, { title, assignee: personas.accounts.regular.name });
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.getByText('Project field is required.', { exact: false })).toBeVisible();
  await expect(page).toHaveURL(/\/node\/add\/task$/);
  await expect(page.getByLabel('Title', { exact: true })).toHaveValue(title);
  await page.goto(`/project/${scenario.projects.kanban}/board`);
  await expect(page.getByText(title, { exact: true })).toHaveCount(0);
});

test('Original Content Moderation action persists transitions independently of normal Save', async ({ page, scenario, personas }) => {
  const project = await createProject(page, scenario);
  const task = await createTask(page, scenario, personas, project, 'moderation');
  await page.reload();
  await expect(page.getByRole('article')).toContainText(/Status\s*Backlog/);
  for (const [label, status, column] of [
    ['In progress', 'In progress', 'in_progress'],
    ['Review', 'Review', 'review'],
    ['Done', 'Done', 'done'],
    ['In progress', 'In progress', 'in_progress'],
  ]) {
    await page.goto(`${task.path}/edit`);
    await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeVisible();
    const changeTo = page.getByLabel('Change to', { exact: true });
    if (column === 'done') {
      // Review has both approval and reopen: changing the widget must update
      // the original submit action without executing a transition.
      await changeTo.selectOption({ label: 'In progress' });
      await expect(page.getByRole('button', { name: 'Save as In progress', exact: true })).toBeVisible();
    }
    if (await changeTo.inputValue() !== column) await changeTo.selectOption({ label });
    const transition = page.getByRole('button', { name: `Save as ${label}`, exact: true });
    await expect(transition).toBeVisible();
    await transition.click();
    await expect(page).toHaveURL(new RegExp(`${task.path}$`));
    await expect(page.getByRole('article')).toContainText(new RegExp(`Status\\s*${status}`));
    await page.reload();
    await expect(page.getByRole('article')).toContainText(new RegExp(`Status\\s*${status}`));
    await page.goto(`/project/${project.id}/board`);
    await expectInColumn(page, task, column);
  }
});
