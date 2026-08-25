/**
 * Point d'entrée dédié à ApexCharts — chargé UNIQUEMENT par les pages qui rendent un
 * graphique, via `@push('scripts') @vite(['resources/js/apexcharts.js'])`. La bibliothèque
 * pèse 575 ko : la laisser dans `app.js` la faisait payer à chaque page.
 *
 * LE THÈME EST POSÉ ICI, PAS DANS CHAQUE VUE.
 *
 * Cinq vues configuraient leurs couleurs à la main, chacune à sa façon : une grille grise
 * ici, une police système là, et rien qui suive le mode sombre. Les valeurs par défaut
 * d'ApexCharts s'appliquent à TOUT graphique construit après ce module — une vue n'a donc
 * plus rien à déclarer pour être dans le thème, et ce qu'elle déclare l'emporte toujours.
 */
import ApexCharts from 'apexcharts';

const jeton = (nom, repli) =>
  (typeof window.brioJeton === 'function' ? window.brioJeton(nom, repli) : repli) || repli;

/**
 * La palette d'un graphique : l'ambre de la marque en tête, puis des teintes qui s'en
 * éloignent assez pour rester distinguables — y compris pour un daltonien, ce qu'une
 * rampe d'une seule teinte ne permet jamais.
 */
const palette = () => [
  jeton('--cx-amber', '#ffb648'),
  jeton('--brio-info', '#2563eb'),
  jeton('--cx-cyan', '#4fe3d6'),
  jeton('--cx-violet', '#8b7bff'),
  jeton('--brio-success', '#059669'),
  jeton('--cx-amber-deep', '#ff8a3d'),
];

const appliquerLeTheme = () => {
  const attenue = jeton('--brio-muted', '#5b6b85');
  const bordure = jeton('--brio-border', 'rgba(148,163,184,0.18)');
  const sombre = document.documentElement.classList.contains('dark');

  window.Apex = {
    chart: {
      fontFamily: 'Sora, Figtree, system-ui, sans-serif',
      foreColor: attenue,
      background: 'transparent',
      toolbar: { show: false },
      // Une seule courbe pour tout ce qui bouge, comme en CSS : deux graphiques
      // voisins qui n'ont pas la même animation paraissent décousus.
      animations: { enabled: true, easing: 'easeinout', speed: 420, animateGradually: { enabled: false } },
    },
    colors: palette(),
    theme: { mode: sombre ? 'dark' : 'light' },
    grid: {
      borderColor: bordure,
      strokeDashArray: 3,
      padding: { left: 4, right: 4 },
    },
    stroke: { width: 2.5, curve: 'smooth', lineCap: 'round' },
    dataLabels: { enabled: false },
    legend: {
      labels: { colors: attenue },
      markers: { width: 9, height: 9, radius: 9 },
      itemMargin: { horizontal: 10, vertical: 4 },
    },
    tooltip: {
      theme: sombre ? 'dark' : 'light',
      style: { fontFamily: 'Sora, Figtree, system-ui, sans-serif' },
    },
    xaxis: {
      axisBorder: { color: bordure },
      axisTicks: { color: bordure },
      labels: { style: { colors: attenue, fontSize: '11px' } },
    },
    yaxis: {
      labels: { style: { colors: attenue, fontSize: '11px' } },
    },
    // Le remplissage en dégradé donne la profondeur des tableaux de bord primés :
    // une aire pleine et plate écrase la courbe qui la borde.
    fill: {
      type: 'gradient',
      gradient: { shadeIntensity: 0.35, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 90, 100] },
    },
    states: {
      hover: { filter: { type: 'lighten', value: 0.06 } },
      active: { filter: { type: 'darken', value: 0.08 } },
    },
    noData: {
      text: 'Aucune donnée sur cette période',
      style: { color: attenue, fontFamily: 'Sora, Figtree, system-ui, sans-serif', fontSize: '13px' },
    },
  };
};

appliquerLeTheme();
window.ApexCharts = ApexCharts;

/*
 * LE THÈME SUIT LA BASCULE. `<x-theme-amorce />` émet `brio:theme` quand l'utilisateur
 * change de mode : sans cette écoute, les graphiques déjà construits gardent les couleurs
 * du mode précédent — texte sombre sur fond de nuit — jusqu'au prochain rechargement.
 */
document.addEventListener('brio:theme', () => {
  appliquerLeTheme();

  document.querySelectorAll('.apexcharts-canvas').forEach((canvas) => {
    const graphique = ApexCharts.getChartByID?.(canvas.id?.replace('apexcharts', ''));
    graphique?.updateOptions?.(
      { theme: window.Apex.theme, chart: { foreColor: window.Apex.chart.foreColor }, grid: window.Apex.grid },
      false,
      false,
    );
  });
});

/**
 * DESSINE UNE COURBE D'ACTIVITÉ À PARTIR DES ATTRIBUTS `data-*` DE SA CIBLE.
 *
 * Les données passent par le DOM, pas par une expression Blade dans `x-init`. La raison est
 * concrète : une expression imbriquée à l'intérieur d'une directive Blade — un `array_sum`
 * d'un `array_column`, ou une boucle qui totalise — casse la compilation de la vue ENTIÈRE,
 * et l'erreur remonte des dizaines de lignes plus bas sur une boucle qui n'y est pour rien.
 *
 * En passant par `data-*`, la vue ne fait plus qu'écrire du JSON dans un attribut, ce que
 * Blade sait faire sans jamais se tromper.
 */
window.dessinerActivite = (racine) => {
  const dessiner = () => {
    const cible = racine.querySelector('[data-graphique]');

    if (!cible || typeof ApexCharts === 'undefined') return;

    let totaux = [];
    let libelles = [];

    try {
      totaux = JSON.parse(cible.dataset.totaux || '[]');
      libelles = JSON.parse(cible.dataset.libelles || '[]');
    } catch (e) {
      return;
    }

    if (!totaux.length) return;

    cible.innerHTML = '';

    /*
     * UNE SERIE D'ARGENT NE SE LIT PAS COMME UN COMPTE.
     *
     * L'axe arrondissait en entiers nus : juste pour « douze missions », muet pour « 12 400 »
     * qui peut etre des euros comme des dirhams. `data-devise` reste FACULTATIF — sans lui, le
     * comportement d'origine est inchange, et aucun appelant existant ne bouge.
     */
    const devise = cible.dataset.devise || '';
    const langue = document.documentElement.lang || 'fr-BE';
    const enArgent = (v) =>
      new Intl.NumberFormat(langue, {
        style: 'currency',
        currency: devise,
        maximumFractionDigits: 0,
      }).format(v);

    const formateur = devise ? enArgent : (v) => Math.round(v);

    new ApexCharts(cible, {
      chart: { type: 'bar', height: 240 },
      plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
      series: [{ name: cible.dataset.nom || '', data: totaux }],
      xaxis: { categories: libelles },
      yaxis: { min: 0, tickAmount: 4, labels: { formatter: formateur } },
      tooltip: { y: { formatter: formateur } },
      fill: { type: 'solid', opacity: 0.9 },
    }).render();
  };

  dessiner();
  document.addEventListener('brio:theme', dessiner);
};

/**
 * DESSINE UN ANNEAU DE RÉPARTITION à partir des attributs `data-*` de sa cible.
 *
 * Un anneau et non un camembert : le trou central rend les proportions comparables par
 * l'angle seul, là où un disque plein invite à comparer des aires — ce que l'œil fait mal.
 *
 * LES PARTS NULLES SONT RETIRÉES. ApexCharts leur réserve une place dans la légende et une
 * étiquette au survol : sur une plateforme où quatre rôles sur six sont vides au démarrage,
 * la légende devient plus longue que le graphique et n'apprend rien.
 */
window.dessinerRepartition = (racine) => {
  const dessiner = () => {
    const cible = racine.querySelector('[data-graphique]');

    if (!cible || typeof ApexCharts === 'undefined') return;

    let valeurs = [];
    let libelles = [];

    try {
      valeurs = JSON.parse(cible.dataset.valeurs || '[]');
      libelles = JSON.parse(cible.dataset.libelles || '[]');
    } catch (e) {
      return;
    }

    const garde = valeurs
      .map((v, i) => ({ v, l: libelles[i] ?? '' }))
      .filter((p) => p.v > 0);

    if (!garde.length) return;

    cible.innerHTML = '';

    new ApexCharts(cible, {
      chart: { type: 'donut', height: 280 },
      series: garde.map((p) => p.v),
      labels: garde.map((p) => p.l),
      legend: { position: 'bottom' },
      stroke: { width: 0 },
      // Le dégradé du thème global écraserait les parts : un anneau se lit en aplats.
      fill: { type: 'solid' },
      plotOptions: {
        pie: {
          donut: {
            size: '68%',
            labels: {
              show: true,
              total: {
                show: true,
                label: 'Total',
                formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0),
              },
            },
          },
        },
      },
      responsive: [{ breakpoint: 480, options: { chart: { height: 240 }, legend: { fontSize: '11px' } } }],
    }).render();
  };

  dessiner();
  document.addEventListener('brio:theme', dessiner);
};
