// Convertit un fichier Markdown en PDF via Chromium/Playwright.
// Usage: node scripts/md-to-pdf.mjs <input.md> <output.pdf>
import { readFileSync } from 'node:fs';
import pw from '../tools/visual-qa/node_modules/playwright/index.js';
const { chromium } = pw;

const [, , inPath, outPath] = process.argv;
if (!inPath || !outPath) {
  console.error('Usage: node scripts/md-to-pdf.mjs <input.md> <output.pdf>');
  process.exit(1);
}

const md = readFileSync(inPath, 'utf8');

const esc = (s) =>
  s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

// Inline: code, bold, italic (appliqué après échappement HTML)
function inline(s) {
  let t = esc(s);
  t = t.replace(/`([^`]+)`/g, (_, c) => `<code>${c}</code>`);
  t = t.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
  t = t.replace(/(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>');
  return t;
}

const lines = md.split(/\r?\n/);
const html = [];
let i = 0;
let inList = false;

const closeList = () => {
  if (inList) {
    html.push('</ul>');
    inList = false;
  }
};

while (i < lines.length) {
  const line = lines[i];

  // Table: ligne avec | suivie d'une ligne séparatrice ---|---
  if (/^\s*\|.*\|\s*$/.test(line) && i + 1 < lines.length && /^\s*\|?[\s:|-]+\|?\s*$/.test(lines[i + 1]) && lines[i + 1].includes('-')) {
    closeList();
    const header = line.trim().replace(/^\||\|$/g, '').split('|').map((c) => c.trim());
    i += 2; // skip header + separator
    const rows = [];
    while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) {
      rows.push(lines[i].trim().replace(/^\||\|$/g, '').split('|').map((c) => c.trim()));
      i++;
    }
    html.push('<table>');
    html.push('<thead><tr>' + header.map((h) => `<th>${inline(h)}</th>`).join('') + '</tr></thead>');
    html.push('<tbody>');
    for (const r of rows) html.push('<tr>' + r.map((c) => `<td>${inline(c)}</td>`).join('') + '</tr>');
    html.push('</tbody></table>');
    continue;
  }

  // Headings
  const h = line.match(/^(#{1,6})\s+(.*)$/);
  if (h) {
    closeList();
    const lvl = h[1].length;
    html.push(`<h${lvl}>${inline(h[2])}</h${lvl}>`);
    i++;
    continue;
  }

  // HR
  if (/^\s*---\s*$/.test(line)) {
    closeList();
    html.push('<hr/>');
    i++;
    continue;
  }

  // Blockquote
  if (/^\s*>\s?/.test(line)) {
    closeList();
    const buf = [];
    while (i < lines.length && /^\s*>\s?/.test(lines[i])) {
      buf.push(lines[i].replace(/^\s*>\s?/, ''));
      i++;
    }
    html.push(`<blockquote>${inline(buf.join(' '))}</blockquote>`);
    continue;
  }

  // List item
  if (/^\s*[-*]\s+/.test(line)) {
    if (!inList) {
      html.push('<ul>');
      inList = true;
    }
    html.push(`<li>${inline(line.replace(/^\s*[-*]\s+/, ''))}</li>`);
    i++;
    continue;
  }

  // Blank
  if (/^\s*$/.test(line)) {
    closeList();
    i++;
    continue;
  }

  // Paragraph (regroupe les lignes consécutives)
  closeList();
  const buf = [line];
  i++;
  while (
    i < lines.length &&
    !/^\s*$/.test(lines[i]) &&
    !/^#{1,6}\s/.test(lines[i]) &&
    !/^\s*[-*]\s+/.test(lines[i]) &&
    !/^\s*\|.*\|\s*$/.test(lines[i]) &&
    !/^\s*>\s?/.test(lines[i]) &&
    !/^\s*---\s*$/.test(lines[i])
  ) {
    buf.push(lines[i]);
    i++;
  }
  html.push(`<p>${inline(buf.join(' '))}</p>`);
}
closeList();

const doc = `<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"/>
<style>
  @page { margin: 18mm 15mm; }
  * { box-sizing: border-box; }
  body { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 10.5px; line-height: 1.5; color: #1a1a2e; }
  h1 { font-size: 22px; border-bottom: 3px solid #2563eb; padding-bottom: 8px; margin: 0 0 16px; color: #0f172a; }
  h2 { font-size: 16px; margin: 22px 0 8px; color: #1e3a8a; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
  h3 { font-size: 13px; margin: 16px 0 6px; color: #1e40af; }
  p { margin: 6px 0; }
  ul { margin: 6px 0; padding-left: 20px; }
  li { margin: 3px 0; }
  code { background: #f1f5f9; padding: 1px 5px; border-radius: 3px; font-family: "Cascadia Code", Consolas, "Courier New", monospace; font-size: 9px; color: #be123c; word-break: break-word; }
  strong { color: #0f172a; }
  blockquote { border-left: 4px solid #94a3b8; margin: 10px 0; padding: 4px 12px; background: #f8fafc; color: #475569; font-style: italic; }
  hr { border: none; border-top: 1px solid #e2e8f0; margin: 18px 0; }
  table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 9px; }
  th, td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: left; vertical-align: top; }
  th { background: #1e3a8a; color: #fff; font-weight: 600; }
  tr:nth-child(even) td { background: #f8fafc; }
  h3 + p code, p code { white-space: nowrap; }
</style></head><body>${html.join('\n')}</body></html>`;

const browser = await chromium.launch();
const page = await browser.newPage();
await page.setContent(doc, { waitUntil: 'networkidle' });
await page.pdf({
  path: outPath,
  format: 'A4',
  printBackground: true,
  displayHeaderFooter: true,
  headerTemplate: '<span></span>',
  footerTemplate:
    '<div style="font-size:8px;color:#94a3b8;width:100%;text-align:center;">CleanUx — Audit plateforme 2026-06-08 — page <span class="pageNumber"></span>/<span class="totalPages"></span></div>',
  margin: { top: '18mm', bottom: '16mm', left: '15mm', right: '15mm' },
});
await browser.close();
console.log('PDF généré: ' + outPath);
