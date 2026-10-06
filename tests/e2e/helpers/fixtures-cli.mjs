import { randomBytes } from 'node:crypto';
import { drupal } from './drupal.mjs';

const operation = process.argv[2];
if (process.argv.includes('--list')) {
  console.log(await drupal('list', 'list'));
  process.exit(0);
}
const position = process.argv.indexOf('--namespace');
const namespace = position >= 0 ? process.argv[position + 1] : undefined;
if (!namespace || !['seed', 'reset'].includes(operation)) throw new Error('Use seed/reset --namespace NAME [--media]');
if (operation === 'reset') console.log(await drupal('cleanup', namespace));
else {
  const accounts = await drupal('accounts', namespace, {
    managerPassword: process.env.E2E_MANAGER_PASSWORD || randomBytes(24).toString('hex'),
    userPassword: process.env.E2E_USER_PASSWORD || randomBytes(24).toString('hex'),
  });
  console.log({ accounts, ...await drupal('scenario', namespace, { media: process.argv.includes('--media') }) });
}
