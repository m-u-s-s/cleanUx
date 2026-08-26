// tools/visual-qa/report.mjs
import { mkdirSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const C = ['c1_no_h_scroll', 'c2_tap_targets', 'c3_readable_text', 'c4_no_broken_layout', 'c5_nav_chrome_absent', 'c6_fond_suit_le_theme'];
const LABEL = { c1_no_h_scroll: 'C1', c2_tap_targets: 'C2', c3_readable_text: 'C3', c4_no_broken_layout: 'C4', c5_nav_chrome_absent: 'C5', c6_fond_suit_le_theme: 'C6' };

export function writeReport(results) {
  const outDir = resolve(__dirname, 'out');
  mkdirSync(outDir, { recursive: true });
  writeFileSync(resolve(outDir, 'report.json'), JSON.stringify(results, null, 2));

  const passed = results.filter((r) => r.pass).length;
  const failed = results.filter((r) => !r.pass);
  let md = `# Visual QA report\n\n${passed}/${results.length} pages PASS.\n\n`;
  md += `| Page | Role | HTTP | ${C.map((c) => LABEL[c]).join(' | ')} | Pass |\n`;
  md += `|---|---|---|${C.map(() => '---').join('|')}|---|\n`;
  for (const r of results.sort((a, b) => `${a.role}${a.key}`.localeCompare(`${b.role}${b.key}`))) {
    const cells = C.map((c) => (r.criteria?.[c] === undefined ? '–' : r.criteria[c] ? '✓' : '✗'));
    md += `| ${r.key} | ${r.role} | ${r.http} | ${cells.join(' | ')} | ${r.pass ? '✅' : '❌'} |\n`;
  }
  if (failed.length) {
    md += `\n## Failures detail\n\n`;
    for (const r of failed) {
      md += `### ${r.key} (${r.role}) — HTTP ${r.http}\n`;
      if (r.error) md += `- error: ${r.error}\n`;
      for (const c of ['c2', 'c3', 'c4', 'c6']) {
        if (r.offenders?.[c]?.length) md += `- ${c}: ${JSON.stringify(r.offenders[c])}\n`;
      }
      md += '\n';
    }
  }
  writeFileSync(resolve(outDir, 'report.md'), md);
  return { passed, total: results.length, failed: failed.length };
}
