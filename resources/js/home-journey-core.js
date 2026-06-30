/* ============================================================================
   CleanUx — Home « Parcours d'une mission » : monde 3D cinématique
   GSAP ScrollTrigger (mouvement de caméra au scroll) + Three.js (monde unique
   à 9 stations : globe pointillé, téléphone 3D avec votre UI à l'écran, et
   plans photo « billboards » pour les vrais humains/objets que vous fournissez).
   ----------------------------------------------------------------------------
   ROBUSTESSE (important — testé en prod) :
   - Bundle chargé UNIQUEMENT sur la home (CSP prod = script-src 'self' : tout
     est self-hosted via Vite, jamais de CDN).
   - prefers-reduced-motion  -> on n'initialise PAS la 3D : la section reste en
     fallback stepper statique (journey.css).
   - WebGL absent / erreur d'init / asset manquant -> on retombe proprement :
     la 3D ne s'active pas (.is-3d non posée) et le scrollytelling SVG existant
     reprend la main. Aucune page cassée.
   - Re-init après navigation Livewire ; cleanup du contexte WebGL avant nav.
   ----------------------------------------------------------------------------
   ASSETS (déposez-les dans public/images/journey/ — voir le README de ce dossier) :
     app-booking.jpg  app-quote.jpg  app-map.jpg  app-done.jpg   (captures app)
     provider.png  client.png  house.png  tools.png              (cutouts PNG transparents)
   Chaque asset est OPTIONNEL : manquant -> placeholder neutre (téléphone gris,
   billboard invisible). Rien ne casse.
   ========================================================================= */

import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import * as THREE from 'three';

gsap.registerPlugin(ScrollTrigger);

const REDUCE = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const ASSET_BASE = '/images/journey/';

/* ----------------------------------------------------------------------------
   Petites fabriques réutilisables
   ------------------------------------------------------------------------- */

// Matériau d'écran : gris foncé tant que l'image n'est pas chargée, puis l'UI.
function screenMaterial(file) {
    const mat = new THREE.MeshBasicMaterial({ color: 0x111827 });
    if (file) {
        new THREE.TextureLoader().load(ASSET_BASE + file, (tex) => {
            tex.colorSpace = THREE.SRGBColorSpace;
            mat.map = tex;
            mat.color.set(0xffffff);
            mat.needsUpdate = true;
        });
    }
    return mat;
}

// Téléphone 3D (primitive réaliste : corps métal + écran UI). Remplaçable plus
// tard par un .glb CC0 sans toucher au reste.
function buildPhone(screenFile, scale) {
    const g = new THREE.Group();
    const body = new THREE.Mesh(
        new THREE.BoxGeometry(1.0, 2.0, 0.11),
        new THREE.MeshStandardMaterial({ color: 0x0b1220, metalness: 0.65, roughness: 0.35 })
    );
    g.add(body);
    const screen = new THREE.Mesh(new THREE.PlaneGeometry(0.88, 1.8), screenMaterial(screenFile));
    screen.position.z = 0.061;
    g.add(screen);
    // reflet discret
    const glare = new THREE.Mesh(
        new THREE.PlaneGeometry(0.88, 1.8),
        new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.05 })
    );
    glare.position.z = 0.063;
    g.add(glare);
    g.scale.setScalar(scale || 1);
    return g;
}

// Billboard photo : plan transparent qui reste invisible si l'image manque.
function buildBillboard(file, w, h) {
    const mat = new THREE.MeshBasicMaterial({ transparent: true, opacity: 0, depthWrite: false });
    if (file) {
        new THREE.TextureLoader().load(ASSET_BASE + file, (tex) => {
            tex.colorSpace = THREE.SRGBColorSpace;
            mat.map = tex;
            mat.opacity = 1;
            mat.needsUpdate = true;
        });
    }
    const mesh = new THREE.Mesh(new THREE.PlaneGeometry(w, h), mat);
    mesh.userData.billboard = true; // face toujours la caméra
    return mesh;
}

// Bloc « prop » coloré (corps de métier du chantier groupé, etc.)
function buildBlock(color, w, h, d) {
    return new THREE.Mesh(
        new THREE.BoxGeometry(w, h, d),
        new THREE.MeshStandardMaterial({ color: color, metalness: 0.1, roughness: 0.7 })
    );
}

// Globe pointillé (station 0) — spirale de Fibonacci + marqueurs pays.
function buildGlobe() {
    const group = new THREE.Group();
    const R = 1.5;
    const N = 1100;
    const pos = new Float32Array(N * 3);
    const golden = Math.PI * (3 - Math.sqrt(5));
    for (let i = 0; i < N; i++) {
        const y = 1 - (i / (N - 1)) * 2;
        const rad = Math.sqrt(Math.max(0, 1 - y * y));
        const th = golden * i;
        pos[i * 3] = Math.cos(th) * rad * R;
        pos[i * 3 + 1] = y * R;
        pos[i * 3 + 2] = Math.sin(th) * rad * R;
    }
    const dg = new THREE.BufferGeometry();
    dg.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    group.add(new THREE.Points(dg, new THREE.PointsMaterial({
        color: 0x6366f1, size: 0.04, sizeAttenuation: true, transparent: true, opacity: 0.85,
    })));
    const halo = new THREE.Mesh(
        new THREE.SphereGeometry(R * 1.02, 32, 32),
        new THREE.MeshBasicMaterial({ color: 0x818cf8, transparent: true, opacity: 0.06 })
    );
    group.add(halo);
    function toVec(lat, lng, r) {
        const phi = (90 - lat) * (Math.PI / 180);
        const theta = (lng + 180) * (Math.PI / 180);
        return [-r * Math.sin(phi) * Math.cos(theta), r * Math.cos(phi), r * Math.sin(phi) * Math.sin(theta)];
    }
    const C = [[50.5, 4.5], [46.6, 2.4], [52.1, 5.3], [51.2, 10.4], [40.4, -3.7],
        [41.9, 12.5], [39.4, -8.2], [49.6, 6.1], [47.6, 14.6]];
    const mp = new Float32Array(C.length * 3);
    C.forEach((c, i) => { const v = toVec(c[0], c[1], R * 1.04); mp[i * 3] = v[0]; mp[i * 3 + 1] = v[1]; mp[i * 3 + 2] = v[2]; });
    const mg = new THREE.BufferGeometry();
    mg.setAttribute('position', new THREE.BufferAttribute(mp, 3));
    group.add(new THREE.Points(mg, new THREE.PointsMaterial({ color: 0xffb648, size: 0.16, sizeAttenuation: true })));
    group.userData.spin = true;
    return group;
}

/* ----------------------------------------------------------------------------
   Définition des 9 stations : chaque "set" 3D construit son décor. Les stations
   sont espacées sur l'axe X ; la caméra voyage de l'une à l'autre au scroll.
   ------------------------------------------------------------------------- */
const STATION_GAP = 14;

function buildStations() {
    const sets = [];

    // 0 — Couverture : le globe
    sets.push((g) => { g.add(buildGlobe()); });

    // 1 — Réserver : téléphone (booking) + client photo
    sets.push((g) => {
        const ph = buildPhone('app-booking.jpg', 1.0); ph.position.set(0.4, 0.4, 0); ph.rotation.y = -0.25; g.add(ph);
        const cl = buildBillboard('client.png', 2.4, 3.4); cl.position.set(-1.8, 0.2, -0.6); g.add(cl);
    });

    // 2 — Devis IA depuis photo : téléphone (quote) + mur/pièce
    sets.push((g) => {
        const ph = buildPhone('app-quote.jpg', 1.1); ph.position.set(0, 0.4, 0.4); g.add(ph);
        const house = buildBillboard('house.png', 3.2, 3.2); house.position.set(0.2, 0.1, -1.4); g.add(house);
    });

    // 3 — Chantier groupé : 4 blocs métiers empilés/orchestrés
    sets.push((g) => {
        const colors = [0x6366f1, 0xf59e0b, 0x9333ea, 0xeab308];
        colors.forEach((c, i) => {
            const b = buildBlock(c, 2.2, 0.5, 1.2);
            b.position.set(0, -0.9 + i * 0.62, 0);
            b.rotation.y = (i % 2 ? 0.12 : -0.12);
            g.add(b);
        });
    });

    // 4 — Tracking : téléphone (carte) qui flotte
    sets.push((g) => {
        const ph = buildPhone('app-map.jpg', 1.25); ph.position.set(0, 0.4, 0); g.add(ph);
        ph.userData.float = true;
    });

    // 5 — Arrivée + QR départ : prestataire + maison + téléphone QR
    sets.push((g) => {
        const pr = buildBillboard('provider.png', 2.4, 3.4); pr.position.set(-1.7, 0.2, 0); g.add(pr);
        const house = buildBillboard('house.png', 3.0, 3.0); house.position.set(1.4, 0.1, -1.2); g.add(house);
        const ph = buildPhone('app-done.jpg', 0.8); ph.position.set(0.2, -0.1, 0.6); ph.rotation.y = 0.3; g.add(ph);
    });

    // 6 — Le travail : prestataire + outils
    sets.push((g) => {
        const pr = buildBillboard('provider.png', 2.6, 3.6); pr.position.set(-0.6, 0.2, 0); g.add(pr);
        const tools = buildBillboard('tools.png', 1.8, 1.8); tools.position.set(1.4, -0.3, 0.4); g.add(tools);
    });

    // 7 — QR fin : client + prestataire + téléphone
    sets.push((g) => {
        const cl = buildBillboard('client.png', 2.2, 3.2); cl.position.set(-1.6, 0.2, 0); g.add(cl);
        const pr = buildBillboard('provider.png', 2.2, 3.2); pr.position.set(1.6, 0.2, 0); g.add(pr);
        const ph = buildPhone('app-done.jpg', 0.8); ph.position.set(0, -0.2, 0.8); g.add(ph);
    });

    // 8 — Poignée de main + paiement : client + prestataire + jeton ✓
    sets.push((g) => {
        const cl = buildBillboard('client.png', 2.2, 3.2); cl.position.set(-1.3, 0.2, 0); g.add(cl);
        const pr = buildBillboard('provider.png', 2.2, 3.2); pr.position.set(1.3, 0.2, 0); g.add(pr);
        const coin = new THREE.Mesh(
            new THREE.CylinderGeometry(0.6, 0.6, 0.12, 40),
            new THREE.MeshStandardMaterial({ color: 0x10b981, metalness: 0.4, roughness: 0.4 })
        );
        coin.position.set(0, 1.4, 0.4); coin.rotation.x = Math.PI / 2; coin.userData.spinY = true; g.add(coin);
    });

    return sets;
}

/* ----------------------------------------------------------------------------
   Construction du monde + boucle de rendu + caméra pilotée au scroll
   ------------------------------------------------------------------------- */
function buildWorld(canvas, section) {
    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    } catch (e) {
        return null; // pas de WebGL -> fallback SVG
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

    const scene = new THREE.Scene();
    scene.fog = new THREE.Fog(0xf6f7ff, 10, 26); // estompe les stations voisines

    const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 120);

    // Lumières (clé + remplissage doux)
    scene.add(new THREE.HemisphereLight(0xffffff, 0xc7d2fe, 1.05));
    const key = new THREE.DirectionalLight(0xffffff, 1.1);
    key.position.set(4, 8, 6);
    scene.add(key);

    // Stations
    const sets = buildStations();
    const groups = [];
    sets.forEach((build, i) => {
        const g = new THREE.Group();
        g.position.x = i * STATION_GAP;
        try { build(g); } catch (e) { /* set défaillant -> station vide, pas de crash */ }
        scene.add(g);
        groups.push(g);
    });
    const N = groups.length;

    function size() {
        const w = canvas.clientWidth || section.clientWidth || 1;
        const h = canvas.clientHeight || window.innerHeight || 1;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    }
    size();

    // État piloté par le scroll
    let progress = 0;          // 0..1 sur toute la section
    let raf = null;
    let running = true;
    const tmpLook = new THREE.Vector3();

    function updateCamera() {
        const seg = progress * (N - 1);
        const i = Math.min(N - 1, Math.floor(seg));
        const cx = i * STATION_GAP;
        const x = progress * (N - 1) * STATION_GAP;
        // léger balancement cinématique + dolly
        const t = performance.now() / 1000;
        camera.position.set(
            x + Math.sin(t * 0.4) * 0.25,
            1.15 + Math.cos(t * 0.33) * 0.12,
            6.2
        );
        tmpLook.set(x, 0.65, 0);
        camera.lookAt(tmpLook);
        return { cx };
    }

    function frame() {
        updateCamera();
        const t = performance.now() / 1000;
        groups.forEach((g) => {
            g.children.forEach((c) => {
                if (c.userData.spin) c.rotation.y += 0.004;
                if (c.userData.spinY) c.rotation.z += 0.02;
                if (c.userData.float) c.position.y = 0.4 + Math.sin(t * 1.2) * 0.12;
                if (c.userData.billboard) c.lookAt(camera.position);
            });
        });
        renderer.render(scene, camera);
        raf = running ? requestAnimationFrame(frame) : null;
    }

    function onResize() { size(); }
    window.addEventListener('resize', onResize, { passive: true });

    running = true;
    frame();

    return {
        setProgress(p) { progress = Math.max(0, Math.min(1, p)); },
        activeIndex() { return Math.round(progress * (N - 1)); },
        count: N,
        pause() { running = false; if (raf != null) { cancelAnimationFrame(raf); raf = null; } },
        resume() { if (!running) { running = true; frame(); } },
        dispose() {
            this.pause();
            window.removeEventListener('resize', onResize);
            renderer.dispose();
            scene.traverse((o) => {
                if (o.geometry) o.geometry.dispose();
                if (o.material) {
                    const m = Array.isArray(o.material) ? o.material : [o.material];
                    m.forEach((mm) => { if (mm.map) mm.map.dispose(); mm.dispose(); });
                }
            });
        },
    };
}

/* ----------------------------------------------------------------------------
   Câblage scroll (ScrollTrigger) <-> caméra + légendes/puces
   ------------------------------------------------------------------------- */
function wireScroll(section, world) {
    const sticky = section.querySelector('.cx-journey__sticky');
    const scenes = Array.prototype.slice.call(section.querySelectorAll('.cx-scene'));
    const dots = Array.prototype.slice.call(section.querySelectorAll('.cx-dot'));
    let current = -1;

    function setStep(i) {
        if (i === current) return;
        current = i;
        scenes.forEach((s, k) => s.classList.toggle('is-active', k === i));
        dots.forEach((d, k) => d.classList.toggle('is-active', k === i));
        const cap = scenes[i] && scenes[i].querySelector('.cx-scene__cap');
        if (cap) gsap.fromTo(cap, { y: 18, opacity: 0 },
            { y: 0, opacity: 1, duration: 0.45, ease: 'power2.out', overwrite: true });
    }

    ScrollTrigger.create({
        trigger: section,
        start: 'top top',
        end: 'bottom bottom',
        scrub: true,
        onUpdate(self) {
            world.setProgress(self.progress);
            if (sticky) sticky.style.setProperty('--p', self.progress.toFixed(4));
            setStep(world.activeIndex());
        },
    });

    const head = section.querySelector('.cx-journey__title');
    if (head) gsap.from(head, {
        scrollTrigger: { trigger: section, start: 'top 80%' },
        y: 24, opacity: 0, duration: 0.7, ease: 'power3.out',
    });

    setStep(0);
    ScrollTrigger.refresh();
}

/* ----------------------------------------------------------------------------
   Cycle de vie
   ------------------------------------------------------------------------- */
let world = null;

export function init() {
    const section = document.querySelector('[data-cx-journey]');
    if (!section || section.dataset.cxJourneyEnhanced) return;
    if (REDUCE) return; // -> fallback stepper statique (CSS)

    const canvas = section.querySelector('[data-cx-journey-canvas]');
    if (!canvas) return; // pas de canvas -> on laisse le SVG (app.js est neutralisé par l'attribut)

    try {
        section.classList.add('is-animated');
        world = buildWorld(canvas, section);
        if (!world) { section.classList.remove('is-animated'); return; } // pas de WebGL -> SVG
        section.classList.add('is-3d'); // masque les visuels SVG, garde les légendes
        section.dataset.cxJourneyEnhanced = '1';
        wireScroll(section, world);
    } catch (e) {
        // Tout échec -> on revient au scrollytelling SVG existant
        if (world) { try { world.dispose(); } catch (_) {} world = null; }
        section.classList.remove('is-3d');
        // on retire is-animated pour réafficher le stepper si rien d'autre ne pilote
        if (!section.dataset.cxJourneyEnhanced) section.classList.remove('is-animated');
    }
}

export function teardown() {
    if (world) { try { world.dispose(); } catch (_) {} world = null; }
    ScrollTrigger.getAll().forEach((t) => t.kill());
    const section = document.querySelector('[data-cx-journey]');
    if (section) {
        delete section.dataset.cxJourneyEnhanced;
        section.classList.remove('is-3d');
    }
}

// Pause le rendu quand l'onglet n'est pas visible (perf/batterie)
document.addEventListener('visibilitychange', () => {
    if (!world) return;
    if (document.hidden) world.pause(); else world.resume();
});

// NB : le cycle de vie (boot / Livewire / idle) est piloté par le loader léger
// resources/js/home-journey.js, qui n'importe ce cœur (three + gsap) que lorsque
// la section approche du viewport.
