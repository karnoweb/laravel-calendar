import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const pages = [
    ['personal', '/calendar/personal'],
    ['dashboard', '/calendar/dashboard'],
    ['operations', '/calendar/operations'],
    ['public', '/calendar/public'],
    ['comprehensive', '/calendar/comprehensive'],
    ['tasks', '/calendar/tasks'],
    ['classes', '/calendar/classes'],
    ['my-calendar', '/calendar/my-calendar'],
    ['education-calendar', '/calendar/education-calendar'],
    ['crm-calendar', '/calendar/crm-calendar'],
    ['delivery-calendar', '/calendar/delivery-calendar'],
    ['overview-calendar', '/calendar/overview-calendar'],
];

const locales = {
    fa: 'fa',
    rtl: 'fa',
    en: 'en',
    ltr: 'en',
};

const themes = { light: 'light', dark: 'dark' };
const sizes = { desktop: 'desktop', mobile: 'mobile' };
const viewports = {
    desktop: { width: 1440, height: 900 },
    mobile: { width: 390, height: 844 },
};

function help() {
    console.log(`Usage:
  npm run shots -- [locale] [theme] [size] [page]

fa and rtl are RTL. en and ltr are LTR.
Repeat a value with commas. Omitted arguments use every option.

  locale   fa | en | rtl | ltr          default: fa,en
  theme    light | dark                 default: light,dark
  size     desktop | mobile             default: desktop,mobile
  page     personal, dashboard, ...     default: all

  --base URL     default: http://127.0.0.1:8765
  --out DIR      default: review-shots

Examples:
  npm run shots -- fa light
  npm run shots -- en dark desktop
  npm run shots -- fa,en light,dark mobile dashboard,personal
`);
}

function collect(argv) {
    const values = {};
    const positionals = [];
    for (let i = 0; i < argv.length; i++) {
        const arg = argv[i];
        if (arg === '--help' || arg === '-h') {
            return { help: true };
        }
        if (!arg.startsWith('--')) {
            positionals.push(arg);
            continue;
        }
        const body = arg.slice(2);
        const eq = body.indexOf('=');
        const key = eq === -1 ? body : body.slice(0, eq);
        let value = eq === -1 ? argv[++i] : body.slice(eq + 1);
        if (value === undefined || value.startsWith('--')) {
            throw new Error(`Missing value for --${key}`);
        }
        values[key] = values[key] === undefined ? value : `${values[key]},${value}`;
    }
    const [locale, theme, size, page, extra] = positionals;
    if (extra !== undefined) {
        throw new Error(`Unexpected argument: ${extra}`);
    }
    values.locale ??= locale;
    values.theme ??= theme;
    values.size ??= size;
    values.page ??= page;

    return values;
}

function pick(raw, table, label) {
    const chosen = [];
    for (const part of raw.split(',').map((item) => item.trim().toLowerCase()).filter(Boolean)) {
        const value = table[part];
        if (value === undefined) {
            throw new Error(`Unknown ${label}: ${part}. Expected ${Object.keys(table).join(', ')}`);
        }
        if (!chosen.includes(value)) {
            chosen.push(value);
        }
    }
    if (chosen.length === 0) {
        throw new Error(`No ${label} selected.`);
    }

    return chosen;
}

const args = collect(process.argv.slice(2));
if (args.help) {
    help();
    process.exit(0);
}

const selectedLocales = pick(args.locale ?? 'fa,en', locales, 'locale');
const selectedThemes = pick(args.theme ?? 'light,dark', themes, 'theme');
const selectedSizes = pick(args.size ?? 'desktop,mobile', sizes, 'size');
const selectedPages = (args.page ?? pages.map(([name]) => name).join(','))
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
const unknownPages = selectedPages.filter((name) => !pages.some(([page]) => page === name));
if (unknownPages.length > 0) {
    throw new Error(`Unknown page: ${unknownPages.join(', ')}. Expected ${pages.map(([name]) => name).join(', ')}`);
}

const base = (args.base ?? 'http://127.0.0.1:8765').replace(/\/$/, '');
const out = path.resolve(args.out ?? path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'review-shots'));
fs.mkdirSync(out, { recursive: true });

const browser = await chromium.launch({ channel: 'chrome', args: ['--no-proxy-server'] });
const context = await browser.newContext();
await context.route(/fonts\.(googleapis|gstatic)\.com/, (route) => route.abort());
const page = await context.newPage();
await page.goto(`${base}/workbench/login/11/101?tz=Asia/Tehran&locale=${selectedLocales[0]}`, { waitUntil: 'commit', timeout: 20000 });

for (const locale of selectedLocales) {
    for (const theme of selectedThemes) {
        await page.goto(`${base}/workbench/settings?theme=${theme}&locale=${locale}&json=1`, { waitUntil: 'commit', timeout: 20000 });
        for (const [name, route] of pages.filter(([name]) => selectedPages.includes(name))) {
            for (const size of selectedSizes) {
                await page.setViewportSize(viewports[size]);
                await page.emulateMedia({ colorScheme: theme === 'dark' ? 'dark' : 'light' });
                const file = path.join(out, `${name}-${locale}-${theme}-${size}.jpg`);
                await page.goto(base + route, { waitUntil: 'commit', timeout: 20000 });
                await page.waitForSelector('.kw-calendar, .kw-mini, .kw-agenda', { timeout: 8000 }).catch(() => {});
                await page.waitForTimeout(600);
                await page.screenshot({ path: file, type: 'jpeg', quality: 72 });
                console.log(file);
            }
        }
    }
}

browser.process?.kill?.();
process.removeAllListeners('exit');
process.exit(0);
