// Headless screenshots of every screen in English and Bangla.
// Language switching is proven by clicking the real language toggle and asserting <html lang>.
// Usage: node scripts/screenshots.mjs   (needs the API on :8000 and Vite on :5173, and a seeded DB)
import fs from 'node:fs'
import path from 'node:path'
import puppeteer from 'puppeteer-core'

const WEB = process.env.WEB_URL || 'http://localhost:5173'
const API = process.env.API_URL || 'http://localhost:8000/api/v1'
const OUT = path.resolve(process.cwd(), '../docs/screenshots')
const CHROME = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
// Credentials come from backend/.env (local demo only); never printed.
const env = Object.fromEntries(fs.readFileSync('../backend/.env', 'utf8').split('\n').filter((l) => l.includes('=')).map((l) => [l.slice(0, l.indexOf('=')), l.slice(l.indexOf('=') + 1).trim()]))
const login = async (email, password) => (await (await fetch(API + '/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email, password }) })).json()).token
const admin = await login(env.SYSTEM_ADMIN_EMAIL, env.SYSTEM_ADMIN_PASSWORD)
if (!admin) throw new Error('system admin login failed; run php artisan db:seed')
const inc = (await (await fetch(API + '/incidents')).json()).data[0].id

const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--no-sandbox', '--lang=en-US'] })
const page = await browser.newPage()
await page.setViewport({ width: 1440, height: 900 })
fs.mkdirSync(OUT, { recursive: true })
const settle = () => new Promise((r) => setTimeout(r, 1800))
const shot = async (name) => { await settle(); const f = path.join(OUT, name + '.png'); await page.screenshot({ path: f }); console.log('saved', f) }
const go = async (p) => { await page.goto(WEB + p, { waitUntil: 'networkidle2' }) }

const publicScreens = [['landing', '/'], ['login', '/login'], ['signup', '/register']]
const appScreens = [['feed', '/feed'], ['map', '/map'], ['incident', '/incidents/' + inc], ['profile', '/profile'], ['settings', '/settings'], ['notifications', '/notifications'], ['admin', '/admin']]

// Start in English with a clean state.
await go('/'); await page.evaluate(() => { localStorage.clear(); localStorage.setItem('rl_lang', 'en'); localStorage.setItem('rl_theme', 'light') })
const run = async (lang) => {
  await page.evaluate(() => localStorage.removeItem('rl_token'))
  for (const [n, p] of publicScreens) { await go(p); await shot(`${n}-${lang}`) }
  await page.evaluate((t) => localStorage.setItem('rl_token', t), admin)
  for (const [n, p] of appScreens) { await go(p); await shot(`${n}-${lang}`) }
}
await run('en')
// Switch language by clicking the visible toggle, then verify.
await go('/feed')
const before = await page.evaluate(() => document.documentElement.lang)
await page.click('[data-testid="lang-toggle"]')
await page.waitForFunction(() => document.documentElement.lang === 'bn', { timeout: 5000 })
const after = await page.evaluate(() => ({ lang: document.documentElement.lang, stored: localStorage.getItem('rl_lang'), sample: document.querySelector('.sidebar.left')?.textContent?.slice(0, 40) }))
console.log('toggle verified:', before, '->', after.lang, '(stored', after.stored + ')', 'sample text:', after.sample)
await run('bn')
// Dark mode sample
await page.evaluate(() => localStorage.setItem('rl_theme', 'dark')); await go('/feed'); await shot('feed-bn-dark')
await browser.close()
