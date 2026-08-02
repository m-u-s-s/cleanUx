// tools/visual-qa/run.mjs
import { chromium } from 'playwright';
import { loadModules } from './modules.mjs';
import { loginAs, checkModule } from './check.mjs';
import { writeReport } from './report.mjs';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';

// Filtre optionnel par clés, séparées par des virgules : vérifier UNE page qu'on vient d'ajouter
// sans rejouer les 122, et sans que le résultat des autres ne noie le signal qu'on cherche.
const ONLY = (process.env.VQA_ONLY ?? '').split(',').map((k) => k.trim()).filter(Boolean);

const run = async () => {
  const mods = loadModules()
    .filter((m) => !m.deferred) // 7 deferred MySQL exclus
    .filter((m) => ONLY.length === 0 || ONLY.includes(m.key));

  if (!mods.length) {
    console.error(`Aucun module à vérifier${ONLY.length ? ` pour ${ONLY.join(', ')}` : ''}.`);
    process.exit(1);
  }
  const byCred = {};
  for (const m of mods) (byCred[m.credKey ?? 'public'] ??= []).push(m);

  const browser = await chromium.launch();
  const results = [];
  for (const [credKey, group] of Object.entries(byCred)) {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    try {
      await loginAs(context, BASE, credKey === 'public' ? null : credKey);
    } catch (e) {
      console.error(`[login ${credKey}] ${e.message}`);
      for (const m of group) results.push({ key: m.key, path: m.path, role: credKey, http: 0, pass: false, criteria: {}, offenders: {}, error: `login failed: ${e.message}` });
      await context.close();
      continue;
    }
    for (const m of group) {
      const r = await checkModule(context, BASE, m);
      results.push(r);
      console.log(`${r.pass ? 'PASS' : 'FAIL'}  ${r.role.padEnd(16)} ${r.key}`);
    }
    await context.close();
  }
  await browser.close();

  const summary = writeReport(results);
  console.log(`\n${summary.passed}/${summary.total} PASS, ${summary.failed} FAIL → out/report.md`);
  process.exit(summary.failed > 0 ? 1 : 0);
};

run();
