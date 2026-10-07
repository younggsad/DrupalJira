import { test, expect } from './fixtures.mjs';

test('task dialog closes by Escape and close control, restores focus, and survives viewport changes', async ({ page, scenario }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto(`/project/${scenario.projects.kanban}/board`);
  const card = page.locator(`.task-card[data-task-id="${scenario.tasks.backlog.id}"]`);
  await card.focus();
  await card.press('Enter');
  await expect(page.getByRole('dialog')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog')).toHaveCount(0);
  await expect(card).toBeFocused();

  await page.setViewportSize({ width: 375, height: 900 });
  await card.press('Enter');
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible();
  await page.setViewportSize({ width: 768, height: 900 });
  await expect(dialog).toBeVisible();
  await dialog.getByRole('button', { name: 'Close', exact: true }).click();
  await expect(page.getByRole('dialog')).toHaveCount(0);
  await expect(card).toBeFocused();
  expect(errors).toEqual([]);
});
