import { expect } from '../fixtures.mjs';
import { drupal } from './drupal.mjs';

export async function track(scenario, bundle, title) {
  await drupal('track', scenario.namespace, { bundle, title });
}
export async function saveNode(page, title) {
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page).toHaveURL(/\/node\/\d+$/);
  await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
  return new URL(page.url()).pathname;
}
export async function createProject(page, scenario, suffix = 'project') {
  const title = `E2E UI ${suffix} ${scenario.namespace}`;
  await track(scenario, 'project', title);
  await page.goto('/node/add/project');
  await page.getByLabel('Title', { exact: true }).fill(title);
  const path = await saveNode(page, title);
  return { title, path, id: path.split('/').pop() };
}
export async function selectReference(page, label, name) {
  await page.getByLabel(label, { exact: true }).fill(name);
  await page.getByRole('listitem').filter({ hasText: name }).click();
}
export async function fillTask(page, { title, project, assignee, body = 'UI journey description.', hours = '3', minutes = '30' }) {
  await page.getByLabel('Title', { exact: true }).fill(title);
  if (project) await selectReference(page, 'Project', project);
  if (assignee) await selectReference(page, 'Assignee', assignee);
  await page.getByLabel('Hours', { exact: true }).fill(hours);
  await page.getByLabel('Minutes', { exact: true }).fill(minutes);
  await page.getByLabel('Body', { exact: true }).fill(body);
}
export async function createTask(page, scenario, personas, project, suffix = 'task') {
  const title = `E2E UI ${suffix} ${scenario.namespace}`;
  await track(scenario, 'task', title);
  await page.goto('/node/add/task');
  await fillTask(page, { title, project: project.title, assignee: personas.accounts.regular.name });
  const path = await saveNode(page, title);
  return { title, path, id: path.split('/').pop() };
}
// Board sections/cards lack accessible names; these existing data attributes are
// the smallest stable hooks. Assertions still check the visible title/state.
export const column = (page, state) => page.locator(`[data-status="${state}"]`);
export const card = (page, task) => page.locator(`[data-task-id="${task.id}"]`);
export async function expectInColumn(page, task, state) {
  const names = { backlog: 'Backlog', in_progress: 'In Progress', review: 'Review', done: 'Done' };
  await expect(column(page, state).getByRole('heading', { name: names[state], exact: true })).toBeVisible();
  await expect(column(page, state).getByText(task.title, { exact: true })).toBeVisible();
}
