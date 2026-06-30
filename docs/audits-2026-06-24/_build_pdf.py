# -*- coding: utf-8 -*-
"""Convertit les audits Markdown en HTML stylé (puis Chrome -> PDF)."""
import os
import markdown

HERE = os.path.dirname(os.path.abspath(__file__))

CSS = """
@page { size: A4; margin: 16mm 15mm 16mm 15mm; }
* { box-sizing: border-box; }
body {
  font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
  font-size: 10.4pt; line-height: 1.5; color: #1f2430; margin: 0;
  -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.cover { border-top: 10px solid var(--accent); padding: 6px 0 14px 0; margin-bottom: 18px; border-bottom: 1px solid #e2e6ee; }
.cover .kicker { font-size: 9pt; letter-spacing: 2px; text-transform: uppercase; color: var(--accent); font-weight: 700; }
.cover h1 { font-size: 25pt; margin: 6px 0 2px 0; color: #11151f; line-height: 1.12; }
.cover .persona { font-size: 13pt; color: #46506a; font-weight: 600; margin: 2px 0 12px 0; }
.cover .meta { width: 100%; border-collapse: collapse; font-size: 9pt; color: #46506a; }
.cover .meta td { padding: 2px 10px 2px 0; vertical-align: top; }
.cover .meta .k { color: #8893a8; font-weight: 600; white-space: nowrap; }
.badgebar { margin: 10px 0 0 0; font-size: 8.6pt; color: #5b6478; }
.legend { background: #f6f8fc; border: 1px solid #e2e6ee; border-radius: 6px; padding: 8px 12px; font-size: 8.8pt; color: #46506a; margin: 0 0 16px 0; }
h2 { font-size: 15pt; color: #11151f; margin: 22px 0 8px 0; padding-bottom: 4px; border-bottom: 2px solid var(--accent); page-break-after: avoid; }
h3 { font-size: 11.6pt; color: #1c2230; margin: 16px 0 5px 0; page-break-after: avoid; }
h4 { font-size: 10.4pt; color: #2b3242; margin: 12px 0 4px 0; page-break-after: avoid; }
p { margin: 6px 0; }
ul, ol { margin: 6px 0 6px 0; padding-left: 20px; }
li { margin: 3px 0; }
strong { color: #11151f; }
code { font-family: 'Cascadia Code', Consolas, monospace; font-size: 8.7pt; background: #eef1f7; padding: 1px 4px; border-radius: 3px; color: #b1004a; }
pre { background: #f6f8fc; border: 1px solid #e2e6ee; border-radius: 6px; padding: 10px 12px; overflow-x: auto; font-size: 8.6pt; line-height: 1.4; }
pre code { background: none; color: #1f2430; padding: 0; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 9pt; page-break-inside: auto; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th { background: var(--accent); color: #fff; text-align: left; padding: 6px 8px; font-weight: 600; }
td { border: 1px solid #e2e6ee; padding: 5px 8px; vertical-align: top; }
tbody tr:nth-child(even) { background: #f8fafd; }
blockquote { margin: 10px 0; padding: 8px 14px; background: #f6f8fc; border-left: 4px solid var(--accent); color: #3a4256; font-size: 9.4pt; }
blockquote p { margin: 3px 0; }
hr { border: none; border-top: 1px solid #e2e6ee; margin: 18px 0; }
a { color: var(--accent); text-decoration: none; }
.callout { border-radius: 6px; padding: 10px 14px; margin: 12px 0; font-size: 9.4pt; page-break-inside: avoid; }
.callout.crit { background: #fdecec; border: 1px solid #f5b5b5; }
.callout.ok { background: #eafaf1; border: 1px solid #ace0c2; }
.callout.warn { background: #fff6e6; border: 1px solid #f3d499; }
.section-break { page-break-before: always; }
"""

TEMPLATE = """<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><style>:root{{--accent:{accent};}}{css}</style></head>
<body>
<div class="cover">
  <div class="kicker">Audit · {kicker}</div>
  <h1>{title}</h1>
  <div class="persona">{persona}</div>
  <table class="meta">
    <tr><td class="k">Projet</td><td>CleanUx / brio — marketplace multi-métiers (Laravel 12 + apps mobiles Expo)</td></tr>
    <tr><td class="k">Destinataire</td><td>{persona}</td></tr>
    <tr><td class="k">Date d'audit</td><td>24 juin 2026</td></tr>
    <tr><td class="k">Référence</td><td>Réactualisation de l'audit plateforme du 8 juin 2026</td></tr>
    <tr><td class="k">Confidentialité</td><td>Document interne — confidentiel</td></tr>
  </table>
</div>
<div class="legend"><strong>Légende sévérité :</strong> &nbsp; 🔴 Critique &nbsp;·&nbsp; 🟠 Élevé &nbsp;·&nbsp; 🟡 Moyen &nbsp;·&nbsp; 🔵 Faible &nbsp;·&nbsp; 🟢 Sain / Corrigé</div>
{body}
</body></html>"""

DOCS = [
    ("AUDIT-01-DEVELOPPEUR", "#2563eb", "Technique", "Audit technique — Qualité de code & dette", "Développeur / Lead technique"),
    ("AUDIT-02-RSSI-SECURITE-RGPD", "#dc2626", "Sécurité & Conformité", "Audit sécurité applicative & conformité RGPD", "RSSI / DPO — Responsable Sécurité & RGPD"),
    ("AUDIT-03-DEVOPS-INFRASTRUCTURE", "#7c3aed", "Infrastructure", "Audit DevOps, déploiement & exploitation", "DevOps / Responsable Infrastructure"),
    ("AUDIT-04-COMPTABLE-FINANCE", "#059669", "Finance", "Audit financier — coûts, comptabilité & risques", "Comptable / Directeur financier"),
    ("AUDIT-05-PRODUCT-OWNER-METIER", "#ea580c", "Produit", "Audit produit — périmètre, parcours & maturité", "Product Owner / Responsable métier"),
    ("AUDIT-06-MANAGER-DIRECTION", "#0f766e", "Pilotage", "Audit projet — santé, risques & gouvernance", "Manager / Chef de projet / Direction"),
    ("PROJECTION-FINANCIERE-POTENTIEL", "#b45309", "Projection financière", "Projection du potentiel de revenus", "Direction / Investisseurs"),
]

md = markdown.Markdown(extensions=["tables", "fenced_code", "sane_lists", "attr_list", "md_in_html"])

for slug, accent, kicker, title, persona in DOCS:
    src = os.path.join(HERE, slug + ".md")
    if not os.path.exists(src):
        print("SKIP (manquant):", slug)
        continue
    with open(src, encoding="utf-8") as f:
        body = md.convert(f.read())
    md.reset()
    html = TEMPLATE.format(accent=accent, css=CSS, kicker=kicker, title=title, persona=persona, body=body)
    out = os.path.join(HERE, slug + ".html")
    with open(out, "w", encoding="utf-8") as f:
        f.write(html)
    print("HTML écrit:", out)
print("OK")
