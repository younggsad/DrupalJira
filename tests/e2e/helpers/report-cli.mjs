import { readFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';

let id = process.env.E2E_ARTIFACT_ID;
if (!id) {
  try { id = (await readFile('.playwright/latest-report.txt', 'utf8')).trim(); }
  catch (error) {
    if (error.code === 'ENOENT') throw new Error('No retained failure report. Reports are kept only after failures.');
    throw error;
  }
}
if (!/^[a-zA-Z0-9-]{1,100}$/.test(id)) throw new Error('Invalid report ID');
const child = spawn('npx', ['playwright', 'show-report', `.playwright/reports/${id}`, ...process.argv.slice(2)], { stdio: 'inherit' });
child.on('exit', code => { process.exitCode = code ?? 1; });
