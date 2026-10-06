import { test, expect } from './fixtures.mjs';

test.use({ fixtureMedia: true });
test('optional Media fixtures render through the public task page', async ({ page, scenario }) => {
  await page.goto(`/node/${scenario.tasks.backlog.id}`);
  const attachments = page.getByRole('article');
  const image = attachments.getByAltText('E2E fixture image');
  await expect(image).toBeVisible();
  await image.scrollIntoViewIfNeeded();
  await expect.poll(() => image.evaluate(element => element.naturalWidth)).toBeGreaterThan(0);
  const document = attachments.getByRole('link', { name: 'fixture.txt', exact: true });
  await expect(document).toBeVisible();
  const response = await page.request.get(await document.getAttribute('href'));
  expect(response.ok()).toBeTruthy();
  expect(await response.text()).toContain('DrupalJira deterministic E2E document.');
});
