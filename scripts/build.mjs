import { build } from 'esbuild';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
mkdirSync(resolve(root, 'public/dist'), { recursive: true });

const shared = { bundle: true, minify: true, logLevel: 'info', legalComments: 'none' };

await build({ ...shared, entryPoints: [resolve(root, 'resources/js/calendar.js')], outfile: resolve(root, 'public/dist/calendar.js'), format: 'iife', target: 'es2020' });
await build({ ...shared, entryPoints: [resolve(root, 'resources/css/calendar.css')], outfile: resolve(root, 'public/dist/calendar.css'), target: 'chrome100' });
