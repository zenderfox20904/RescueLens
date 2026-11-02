// Generates favicon set and PWA icons from public/logo.svg. Run: npm run icons
import sharp from 'sharp'
import { readFileSync, writeFileSync } from 'node:fs'
const svg = readFileSync('public/logo.svg')
const out = {}
for (const s of [16, 32, 48, 180, 192, 512]) out[s] = await sharp(svg, { density: 384 }).resize(s, s).png().toBuffer()
writeFileSync('public/favicon-16x16.png', out[16]); writeFileSync('public/favicon-32x32.png', out[32])
writeFileSync('public/apple-touch-icon.png', out[180])
writeFileSync('public/icons/icon-192.png', out[192]); writeFileSync('public/icons/icon-512.png', out[512])
// Maskable: logo on full bleed blue with safe zone padding
const mask = await sharp({ create: { width: 512, height: 512, channels: 4, background: '#1877F2' } })
  .composite([{ input: await sharp(svg, { density: 384 }).resize(400, 400).png().toBuffer(), top: 56, left: 56 }]).png().toBuffer()
writeFileSync('public/icons/icon-maskable-512.png', mask)
// OG default 1200x630
const og = await sharp({ create: { width: 1200, height: 630, channels: 4, background: '#F0F2F5' } })
  .composite([{ input: await sharp(readFileSync('public/logo-wordmark.svg'), { density: 300 }).resize(1000).png().toBuffer(), gravity: 'center' }]).png().toBuffer()
writeFileSync('public/icons/og-default.png', og)
// favicon.ico with embedded PNGs (16, 32, 48)
const imgs = [16, 32, 48].map(s => ({ s, b: out[s] }))
const header = Buffer.alloc(6); header.writeUInt16LE(0, 0); header.writeUInt16LE(1, 2); header.writeUInt16LE(imgs.length, 4)
let offset = 6 + 16 * imgs.length; const dir = []
for (const { s, b } of imgs) { const e = Buffer.alloc(16); e[0] = s; e[1] = s; e.writeUInt16LE(1, 4); e.writeUInt16LE(32, 6); e.writeUInt32LE(b.length, 8); e.writeUInt32LE(offset, 12); offset += b.length; dir.push(e) }
writeFileSync('public/favicon.ico', Buffer.concat([header, ...dir, ...imgs.map(i => i.b)]))
console.log('icons generated')
