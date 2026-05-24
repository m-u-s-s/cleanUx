/**
 * CleanUx branded icon generator
 * Requires @napi-rs/canvas (installed in repo root node_modules)
 *
 * Client app  : amber  #ffb648 CU monogram on #070b14 night background
 * Provider app: indigo #6366f1 CU monogram on #070b14 night background
 *
 * Output files per app:
 *   icon.png                  1024x1024  app store / home screen
 *   adaptive-icon.png         1024x1024  Android adaptive icon foreground (transparent bg)
 *   android-icon-background.png 1024x1024 Android adaptive icon background (solid fill)
 *   android-icon-monochrome.png 1024x1024 Android 13+ monochrome (white on transparent)
 *   splash-icon.png            200x200   centered on splash screen
 *   favicon.png                48x48     web
 */

'use strict';

const { createCanvas } = require('@napi-rs/canvas');
const fs = require('fs');
const path = require('path');

// ─── Brand tokens ────────────────────────────────────────────────────────────
const NIGHT  = '#070b14';
const AMBER  = '#ffb648';
const INDIGO = '#6366f1';
const WHITE  = '#ffffff';

// ─── Drawing helpers ─────────────────────────────────────────────────────────

/**
 * Rounded-rect path helper (no Path2D needed).
 */
function roundedRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.lineTo(x + w - r, y);
  ctx.arcTo(x + w, y,     x + w, y + r,     r);
  ctx.lineTo(x + w, y + h - r);
  ctx.arcTo(x + w, y + h, x + w - r, y + h, r);
  ctx.lineTo(x + r, y + h);
  ctx.arcTo(x,     y + h, x,     y + h - r, r);
  ctx.lineTo(x,     y + r);
  ctx.arcTo(x,     y,     x + r, y,         r);
  ctx.closePath();
}

/**
 * Draw a "CU" monogram icon with rounded-rect background.
 * @param {number} size  Canvas side length in px
 * @param {string} bg    Background fill colour
 * @param {string} fg    Text fill colour
 * @returns {Buffer}
 */
function drawIcon(size, bg, fg) {
  const canvas = createCanvas(size, size);
  const ctx    = canvas.getContext('2d');
  const r      = size * 0.22;

  // Background
  roundedRect(ctx, 0, 0, size, size, r);
  ctx.fillStyle = bg;
  ctx.fill();

  // "CU" text — two characters, two brand colours for visual interest
  // We use two separate fillText calls so "C" can be fg and "U" slightly offset
  const fontSize = Math.round(size * 0.40);
  ctx.font      = `700 ${fontSize}px Arial, sans-serif`;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';

  if (size >= 64) {
    // Full two-letter monogram with subtle letter-spacing trick
    const cx  = size / 2;
    const cy  = size * 0.52;
    const gap = fontSize * 0.08; // small manual letter-spacing

    // Measure both glyphs
    const cWidth = ctx.measureText('C').width;
    const uWidth = ctx.measureText('U').width;
    const totalW = cWidth + uWidth + gap;

    // "C" — left half, brand accent colour
    ctx.fillStyle = fg;
    ctx.textAlign = 'left';
    ctx.fillText('C', cx - totalW / 2, cy);

    // "U" — right half, slightly lighter tint for depth (10% white mix approach: just use fg)
    ctx.fillStyle = fg;
    ctx.fillText('U', cx - totalW / 2 + cWidth + gap, cy);
  } else {
    // Small sizes (favicon): single "C" is more legible
    ctx.fillStyle = fg;
    ctx.textAlign = 'center';
    ctx.font      = `700 ${Math.round(size * 0.50)}px Arial, sans-serif`;
    ctx.fillText('C', size / 2, size * 0.52);
  }

  return canvas.toBuffer('image/png');
}

/**
 * Android adaptive icon foreground — transparent background, just the monogram.
 * The safe zone is the inner 66% of the 1024px canvas.
 */
function drawAdaptiveFg(fg) {
  const size   = 1024;
  const canvas = createCanvas(size, size);
  const ctx    = canvas.getContext('2d');

  const fontSize = Math.round(size * 0.35); // slightly smaller to stay in safe zone
  ctx.font      = `700 ${fontSize}px Arial, sans-serif`;
  ctx.textAlign = 'left';
  ctx.textBaseline = 'middle';

  const cx  = size / 2;
  const cy  = size * 0.52;
  const gap = fontSize * 0.08;

  const cWidth = ctx.measureText('C').width;
  const uWidth = ctx.measureText('U').width;
  const totalW = cWidth + uWidth + gap;

  ctx.fillStyle = fg;
  ctx.fillText('C', cx - totalW / 2, cy);
  ctx.fillText('U', cx - totalW / 2 + cWidth + gap, cy);

  return canvas.toBuffer('image/png');
}

/**
 * Android adaptive icon background — solid colour square (1024x1024).
 */
function drawAdaptiveBg(bg) {
  const size   = 1024;
  const canvas = createCanvas(size, size);
  const ctx    = canvas.getContext('2d');
  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, size, size);
  return canvas.toBuffer('image/png');
}

/**
 * Android 13 monochrome variant — white on transparent.
 */
function drawMonochrome() {
  return drawAdaptiveFg(WHITE);
}

// ─── Asset manifest ───────────────────────────────────────────────────────────

const ROOT = path.resolve(__dirname, '../..');

const assets = [
  // ── Client (amber) ──────────────────────────────────────────────────────────
  {
    file: 'mobile/client/assets/icon.png',
    buf:  drawIcon(1024, NIGHT, AMBER),
  },
  {
    // app.json references ./assets/adaptive-icon.png as the Android foreground
    file: 'mobile/client/assets/adaptive-icon.png',
    buf:  drawAdaptiveFg(AMBER),
  },
  {
    file: 'mobile/client/assets/android-icon-background.png',
    buf:  drawAdaptiveBg(NIGHT),
  },
  {
    file: 'mobile/client/assets/android-icon-monochrome.png',
    buf:  drawMonochrome(),
  },
  {
    // 200x200 centered on the #070b14 splash screen
    file: 'mobile/client/assets/splash-icon.png',
    buf:  drawIcon(200, NIGHT, AMBER),
  },
  {
    file: 'mobile/client/assets/favicon.png',
    buf:  drawIcon(48, NIGHT, AMBER),
  },

  // ── Provider (indigo) ────────────────────────────────────────────────────────
  {
    file: 'mobile/provider/assets/icon.png',
    buf:  drawIcon(1024, NIGHT, INDIGO),
  },
  {
    file: 'mobile/provider/assets/adaptive-icon.png',
    buf:  drawAdaptiveFg(INDIGO),
  },
  {
    file: 'mobile/provider/assets/android-icon-background.png',
    buf:  drawAdaptiveBg(NIGHT),
  },
  {
    file: 'mobile/provider/assets/android-icon-monochrome.png',
    buf:  drawMonochrome(),
  },
  {
    file: 'mobile/provider/assets/splash-icon.png',
    buf:  drawIcon(200, NIGHT, INDIGO),
  },
  {
    file: 'mobile/provider/assets/favicon.png',
    buf:  drawIcon(48, NIGHT, INDIGO),
  },
];

// ─── Write files ─────────────────────────────────────────────────────────────

let written = 0;
let errors  = 0;

assets.forEach(({ file, buf }) => {
  const abs = path.join(ROOT, file);
  try {
    fs.mkdirSync(path.dirname(abs), { recursive: true });
    fs.writeFileSync(abs, buf);
    console.log(`  ok  ${file}  (${(buf.length / 1024).toFixed(1)} KB)`);
    written++;
  } catch (err) {
    console.error(`  ERR ${file}: ${err.message}`);
    errors++;
  }
});

console.log(`\n${written} files written, ${errors} errors.`);
if (errors === 0) {
  console.log('\nClient : amber  #ffb648 "CU" on #070b14 night');
  console.log('Provider: indigo #6366f1 "CU" on #070b14 night');
}
