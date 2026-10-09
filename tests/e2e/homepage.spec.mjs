import { test, expect } from './fixtures.mjs';

test.describe('Application landing page', () => {
  test.describe('regular user', () => {
    test.use({ persona: 'regular' });

    test('home exposes accessible real project boards without administration actions', async ({ page, scenario }) => {
      const response = await page.goto('/');
      expect(response.status()).toBe(200);
      await expect(page).toHaveURL(new RegExp('/$'));
      await expect(page.getByRole('heading', { name: 'Projects', exact: true })).toBeVisible();
      await expect(page.locator(`a[href="/project/${scenario.projects.kanban}/board"]`)).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Your tasks', exact: true })).toBeVisible();
      await expect(page.getByRole('link', { name: 'Create project', exact: true })).toHaveCount(0);
      await expect(page.getByRole('link', { name: 'Content', exact: true })).toHaveCount(0);
      await expect(page.locator('.drupaljira-sidebar')).toHaveCount(0);
    });
  });

  test.describe('anonymous user', () => {
    test.use({ persona: 'anonymous' });

    test('home offers public projects and sign-in without private account actions', async ({ page, scenario }) => {
      const response = await page.goto('/');
      expect(response.status()).toBe(200);
      await expect(page.getByRole('heading', { name: 'Projects', exact: true })).toBeVisible();
      await expect(page.locator(`a[href="/project/${scenario.projects.kanban}/board"]`)).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Your tasks', exact: true })).toHaveCount(0);
      await expect(page.getByRole('link', { name: 'Create project', exact: true })).toHaveCount(0);
      await expect(page.getByRole('link', { name: 'Sign in', exact: true }).first()).toHaveAttribute('href', /destination=/);
    });
  });
});
