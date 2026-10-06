import { test, expect } from './fixtures.mjs';

test.use({ persona: 'manager', journeyPermissions: true });
test('Custom Log time form persists decimal hours and updates Task statistics', async ({ page, scenario }) => {
  const task = scenario.tasks.backlog;
  await page.goto(`/node/${task.id}`);
  await expect(page.getByRole('article')).toContainText('8 ч. (2 ч. written off, 6 ч. remaining)');
  await page.goto(`/task/${task.id}/log-time`);
  await page.getByLabel('Hours', { exact: true }).fill('1.25');
  await page.getByLabel('Log date', { exact: true }).fill('2020-01-03');
  await page.getByLabel('Notes', { exact: true }).fill(`UI time ${scenario.namespace}`);
  await page.getByRole('button', { name: 'Log time', exact: true }).click();
  await expect(page.getByRole('contentinfo', { name: 'Status message' })).toBeVisible();
  await expect(page.getByRole('contentinfo', { name: 'Status message' })).toContainText('Time has been logged successfully.');
  await expect(page).toHaveURL(new RegExp(`/node/${task.id}$`));
  await page.reload();
  await expect(page.getByRole('article')).toContainText('8 ч. (3.25 ч. written off, 4.75 ч. remaining)');
  await page.goto('/admin/content/time-log');
  const lastPage = page.getByRole('link', { name: 'Last page', exact: true });
  // The theme's pager link has an overlapping pointer hit area. Activate
  // its real keyboard UI while retaining all saved-row assertions.
  if (await lastPage.isVisible()) await lastPage.press('Enter');
  const row = page.getByRole('row').filter({ hasText: task.title }).filter({ hasText: '1.25' });
  await expect(row).toContainText('2020-01-03');
  await page.reload();
  await expect(row).toContainText('1.25');
  await expect(row).toContainText(`UI time ${scenario.namespace}`);
});
