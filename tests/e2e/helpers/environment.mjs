import { existsSync } from 'node:fs';
import { loadEnvFile } from 'node:process';
import { execFileSync } from 'node:child_process';

if (existsSync('.env.e2e')) loadEnvFile('.env.e2e');
const { raw: ddev } = JSON.parse(execFileSync('ddev', ['describe', '-j'], { encoding: 'utf8', timeout: 30_000 }));
const ddevURL = new URL(ddev.primary_url);
if (ddev.name !== 'DrupalJira' || ddev.hostname !== `${ddev.name.toLowerCase()}.ddev.site`
  || ddevURL.protocol !== 'https:' || ddevURL.hostname !== ddev.hostname) {
  throw new Error('E2E requires the supported local DDEV project and its HTTPS origin.');
}
const url = new URL(process.env.BASE_URL || ddevURL.origin);
if (url.origin !== ddevURL.origin || url.pathname !== '/' || url.search || url.hash || url.username || url.password) {
  throw new Error('E2E fixtures support only the configured local DDEV URL. Remote targets require a matching fixture backend.');
}
export const baseURL = url.origin;

// This exemption is confined to the exact, validated local DDEV origin above.
// Never disable Node/global TLS verification or extend this to remote targets.
if (process.env.E2E_IGNORE_HTTPS_ERRORS && !['true', 'false'].includes(process.env.E2E_IGNORE_HTTPS_ERRORS)) {
  throw new Error('E2E_IGNORE_HTTPS_ERRORS must be true or false');
}
export const ignoreHTTPSErrors = url.origin === ddevURL.origin
  && process.env.E2E_IGNORE_HTTPS_ERRORS !== 'false';
