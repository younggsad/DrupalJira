import { randomUUID } from 'node:crypto';
import { mkdir, writeFile, rm, readdir, readFile } from 'node:fs/promises';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { baseURL } from './environment.mjs';

const execute = promisify(execFile);
async function cleanupRuntime(namespace) {
  await rm(`.playwright/auth/${namespace}`, { recursive: true, force: true });
  for (const name of await readdir('.playwright/inputs')) {
    if (!/^[a-f0-9-]+\.json$/.test(name)) continue;
    const path = `.playwright/inputs/${name}`;
    let input;
    try { input = JSON.parse(await readFile(path, 'utf8')); }
    catch { continue; }
    if (input.namespace === namespace) await rm(path, { force: true });
  }
}
export async function drupal(operation, namespace, values = {}) {
  if (!/^[a-zA-Z0-9-]{1,100}$/.test(namespace)) throw new Error('Invalid fixture namespace');
  const directory = '.playwright/inputs';
  await mkdir(directory, { recursive: true, mode: 0o700 });
  const input = `${directory}/${randomUUID()}.json`;
  await writeFile(input, JSON.stringify({ operation, namespace, baseURL, ...values }), { mode: 0o600 });
  try {
    const { stdout } = await execute('ddev', ['drush', 'php:script', 'tests/e2e/drupal/fixtures.php', `--script-path=/var/www/html`, `--`, `/var/www/html/${input}`], { timeout: 120_000, maxBuffer: 1024 * 1024 });
    const line = stdout.split('\n').find(line => line.startsWith('E2E_JSON:'));
    if (!line) throw new Error('Fixture helper returned no manifest');
    const result = JSON.parse(line.slice(9));
    if (operation === 'cleanup') await cleanupRuntime(namespace);
    return result;
  } catch (error) {
    // Input contains credentials: report only helper diagnostics, never the input.
    throw new Error(`Drupal fixture ${operation} failed: ${error.stderr || error.message}`);
  } finally {
    await rm(input, { force: true });
  }
}
