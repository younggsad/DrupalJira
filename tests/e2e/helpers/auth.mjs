import { mkdir } from 'node:fs/promises';
import { join } from 'node:path';

export async function login(page, username, password) {
  await page.goto('/user/login');
  await page.getByLabel('Username', { exact: true }).fill(username);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await Promise.all([
    page.waitForURL(url => url.pathname !== '/user/login'),
    page.getByRole('button', { name: 'Log in', exact: true }).click(),
  ]);
}

export async function loginWithDiagnostics(context, username, password, directory, attach) {
  await context.tracing.start({ screenshots: true, snapshots: true, sources: true });
  let page;
  try {
    page = await context.newPage();
    await login(page, username, password);
    await context.tracing.stop();
  } catch (error) {
    await mkdir(directory, { recursive: true, mode: 0o700 });
    const artifacts = [];
    // Capture failures independently, preserving the original login error even
    // when a crashed page cannot be photographed or an attachment cannot be added.
    for (const [name, contentType, capture] of [
      ['screenshot.png', 'image/png', path => page?.screenshot({ path, fullPage: true, timeout: 5000 })],
      ['trace.zip', 'application/zip', path => context.tracing.stop({ path })],
    ]) {
      const path = join(directory, name);
      if (name === 'screenshot.png' && (!page || page.isClosed())) continue;
      try {
        await capture(path);
        artifacts.push(path);
        try { await attach(name, { path, contentType }); } catch { /* The file remains available locally. */ }
      } catch { /* Preserve the login failure and try the next diagnostic. */ }
    }
    throw new Error(`Authentication failed. Diagnostics: ${artifacts.join(', ') || 'browser unavailable'}`, { cause: error });
  }
}
