/* ============================================================================
   Brio — « Le Film » : le moteur de rendu.
   ----------------------------------------------------------------------------
   La matière est une séquence de 350 photogrammes ; le moteur, lui, est resté
   celui de la 3D. Le globe et les neuf stations ont disparu : la scène est
   désormais UN plan plein cadre texturé par le film, un champ de poussière d'or
   en avant-plan (vraie parallaxe, vraie profondeur), et un shader qui repose le
   grain, la vignette, l'aberration chromatique et la montée des hautes lumières.

   POURQUOI UN CANVAS 2D AU MILIEU
   Le fondu entre deux photogrammes voisins se fait sur un canvas 2D, et c'est
   CE canvas qui monte au GPU. Garder 350 textures 1280x720 en mémoire graphique
   coûterait 1,2 Go ; ici une seule texture vit, réécrite quand l'image change.
   En prime, le repli sans WebGL réutilise exactement le même canvas.

   REPLIS (dans l'ordre)
     1. pas de WebGL          -> le canvas 2D est peint directement (sans le look)
     2. pas d'AVIF / réseau   -> rien n'est chargé, le poster et le stepper restent
     3. prefers-reduced-motion-> le module n'est jamais chargé (voir journey-film.js)
   ========================================================================= */

import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import * as THREE from 'three';

import { avifDisponible, chargerManifeste, creerMagasin } from './journey-film-frames';

gsap.registerPlugin(ScrollTrigger);

const LARGEUR_MOBILE = 900;

/* ---------------------------------------------------------------------------
   Le compositeur : deux photogrammes voisins, un fondu, un canvas.
   ------------------------------------------------------------------------ */
function creerCompositeur(largeur, hauteur) {
    const canvas = document.createElement('canvas');
    canvas.width = largeur;
    canvas.height = hauteur;
    const ctx = canvas.getContext('2d', { alpha: false });
    let signature = '';

    return {
        canvas,
        largeur,
        hauteur,

        /** Retourne vrai si le canvas a réellement changé — inutile de re-téléverser sinon. */
        peindre({ a, b, melange }) {
            if (!a) return false;

            const cle = `${a.src}|${b ? b.src : ''}|${melange.toFixed(3)}`;
            if (cle === signature) return false;
            signature = cle;

            ctx.globalAlpha = 1;
            ctx.drawImage(a, 0, 0, largeur, hauteur);

            if (b && b !== a && melange > 0.001) {
                ctx.globalAlpha = melange;
                ctx.drawImage(b, 0, 0, largeur, hauteur);
                ctx.globalAlpha = 1;
            }

            return true;
        },
    };
}

/* ---------------------------------------------------------------------------
   La poussière d'or : un sprite rond dessiné à la volée, pas un fichier.
   ------------------------------------------------------------------------ */
function spriteRond() {
    const c = document.createElement('canvas');
    c.width = c.height = 64;
    const g = c.getContext('2d');
    const degrade = g.createRadialGradient(32, 32, 0, 32, 32, 32);
    degrade.addColorStop(0, 'rgba(255,255,255,1)');
    degrade.addColorStop(0.35, 'rgba(255,214,150,0.65)');
    degrade.addColorStop(1, 'rgba(255,182,72,0)');
    g.fillStyle = degrade;
    g.fillRect(0, 0, 64, 64);

    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
}

function creerPoussiere(nombre) {
    const positions = new Float32Array(nombre * 3);
    const tailles = new Float32Array(nombre);

    for (let i = 0; i < nombre; i++) {
        positions[i * 3] = (Math.random() - 0.5) * 14;
        positions[i * 3 + 1] = (Math.random() - 0.5) * 9;
        positions[i * 3 + 2] = Math.random() * 3.2 + 0.3; // devant le plan du film
        tailles[i] = Math.random() * 0.05 + 0.012;
    }

    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    geo.setAttribute('taille', new THREE.BufferAttribute(tailles, 1));

    const mat = new THREE.PointsMaterial({
        map: spriteRond(),
        size: 0.06,
        sizeAttenuation: true,
        transparent: true,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
        opacity: 0.55,
    });

    return new THREE.Points(geo, mat);
}

/* ---------------------------------------------------------------------------
   Le shader du plan : le look tient ici, et nulle part ailleurs.
   ------------------------------------------------------------------------ */
const VERTEX = `
varying vec2 vUv;
void main() {
    vUv = uv;
    gl_Position = vec4(position.xy, 0.0, 1.0);
}
`;

const FRAGMENT = `
precision highp float;

uniform sampler2D uFilm;
uniform vec2  uCouverture;   // recadrage « cover »
uniform float uTemps;
uniform float uGrain;
uniform float uVignette;
uniform float uAberration;
uniform float uBloom;
uniform float uCourbure;
uniform float uOuverture;    // 0 -> noir, 1 -> plein : l'entrée et la sortie du film

varying vec2 vUv;

float bruit(vec2 p) {
    return fract(sin(dot(p, vec2(12.9898, 78.233))) * 43758.5453);
}

const vec3 ENCRE = vec3(0.020, 0.027, 0.051); // le noir de la salle, cf. --cx-film-nuit

vec3 lire(vec2 centre) {
    vec2 uv = centre * uCouverture + 0.5;

    // HORS CADRE, ON REND L'ENCRE — pas le pixel de bord etire. En portrait le film est
    // volontairement en bande : sans ce test, les bords se seraient etales en trainees.
    if (uv.x < 0.0 || uv.x > 1.0 || uv.y < 0.0 || uv.y > 1.0) return ENCRE;

    return texture2D(uFilm, uv).rgb;
}

void main() {
    vec2 centre = vUv - 0.5;
    float r2 = dot(centre, centre);

    // Courbure : imperceptible, mais elle enlève au plan son air de rectangle collé.
    centre *= 1.0 + uCourbure * r2;

    // Aberration chromatique : nulle au centre, elle ne se voit qu'aux bords.
    float ab = uAberration * r2;
    vec3 couleur;
    couleur.r = lire(centre * (1.0 + ab)).r;
    couleur.g = lire(centre).g;
    couleur.b = lire(centre * (1.0 - ab)).b;

    // Les hautes lumières remontent : les lampadaires et les écrans respirent.
    float luma = dot(couleur, vec3(0.2126, 0.7152, 0.0722));
    couleur += couleur * smoothstep(0.60, 1.0, luma) * uBloom;

    couleur *= 1.0 - uVignette * smoothstep(0.22, 0.92, r2);

    // Grain argentique : c'est LUI qui autorise un AVIF très compressé en dessous.
    float g = bruit(vUv * vec2(1280.0, 720.0) + fract(uTemps) * 91.7) - 0.5;
    couleur += g * uGrain;

    gl_FragColor = vec4(couleur * uOuverture, 1.0);
}
`;

/* ---------------------------------------------------------------------------
   Le monde
   ------------------------------------------------------------------------ */
function construireMonde(canvas, compositeur) {
    let renderer;

    try {
        renderer = new THREE.WebGLRenderer({
            canvas,
            antialias: false,
            alpha: false,
            powerPreference: 'high-performance',
        });
    } catch (e) {
        return null; // pas de WebGL -> l'appelant bascule sur le rendu 2D
    }

    if (!renderer.getContext()) return null;

    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.autoClear = false;

    const texture = new THREE.CanvasTexture(compositeur.canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.minFilter = THREE.LinearFilter;
    texture.magFilter = THREE.LinearFilter;
    texture.generateMipmaps = false;

    const uniforms = {
        uFilm: { value: texture },
        uCouverture: { value: new THREE.Vector2(1, 1) },
        uTemps: { value: 0 },
        // REGLE POUR DU PLEIN JOUR. La vignette etait a 0,55 et le bloom a 0,35 : ecrits pour
        // des plans nocturnes, ils assombrissaient les bords et bavaient sur les hautes
        // lumieres d'une image diurne. Le grain, lui, ne bouge pas : c'est lui qui autorise
        // un AVIF tres compresse en dessous.
        uGrain: { value: 0.048 },
        uVignette: { value: 0.24 },
        uAberration: { value: 0.005 },
        uBloom: { value: 0.16 },
        uCourbure: { value: 0.045 },
        uOuverture: { value: 1 },
    };

    // Le film : un quad plein écran, caméra orthographique. Rien de plus.
    const sceneFilm = new THREE.Scene();
    const cameraFilm = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);
    sceneFilm.add(new THREE.Mesh(
        new THREE.PlaneGeometry(2, 2),
        new THREE.ShaderMaterial({ vertexShader: VERTEX, fragmentShader: FRAGMENT, uniforms, depthTest: false }),
    ));

    // La poussière : caméra perspective, donc vraie parallaxe quand elle dérive.
    const scenePoussiere = new THREE.Scene();
    const cameraPoussiere = new THREE.PerspectiveCamera(50, 1, 0.1, 40);
    cameraPoussiere.position.set(0, 0, 6);
    const poussiere = creerPoussiere(900);
    scenePoussiere.add(poussiere);

    let largeur = 1;
    let hauteur = 1;

    function dimensionner() {
        largeur = canvas.clientWidth || 1;
        hauteur = canvas.clientHeight || 1;
        renderer.setSize(largeur, hauteur, false);

        // CADRAGE : « cover » plein cadre en paysage, PARTIEL en portrait.
        // Un 16:9 recadré en « cover » dans un écran 9:19 ne montre plus que 26 % de la
        // largeur : mesuré, les deux personnes du plan de la poignée de main sortaient du
        // champ. On glisse donc vers le « contain » à mesure que l'écran s'allonge, et le
        // film devient une bande cinéma cernée d'encre — un choix, pas un accident.
        const ratioFilm = compositeur.largeur / compositeur.hauteur;
        const ratioVue = largeur / hauteur;

        let couvX;
        let couvY;

        if (ratioVue > ratioFilm) {
            couvX = 1;
            couvY = ratioFilm / ratioVue;
        } else {
            const glissement = THREE.MathUtils.clamp((ratioFilm / ratioVue - 1) * 0.22, 0, 0.42);
            couvX = THREE.MathUtils.lerp(ratioVue / ratioFilm, 1, glissement);
            couvY = THREE.MathUtils.lerp(1, ratioFilm / ratioVue, glissement);
        }

        uniforms.uCouverture.value.set(couvX, couvY);

        cameraPoussiere.aspect = ratioVue;
        cameraPoussiere.updateProjectionMatrix();
    }

    dimensionner();

    return {
        moteur: 'webgl',

        rafraichirTexture() {
            texture.needsUpdate = true;
        },

        /** @param {number} position 0..1 sur tout le film */
        placer(position) {
            // La poussière dérive à contre-sens du scroll : c'est la parallaxe.
            poussiere.position.y = position * 1.6;
            poussiere.rotation.z = position * 0.25;
            cameraPoussiere.position.x = Math.sin(position * Math.PI * 2) * 0.18;

            // Le film s'ouvre et se ferme sur du noir aux deux extrémités de la section.
            const bords = Math.min(position, 1 - position);
            uniforms.uOuverture.value = THREE.MathUtils.clamp(bords / 0.035, 0, 1);
        },

        rendre(temps) {
            uniforms.uTemps.value = temps;
            renderer.clear();
            renderer.render(sceneFilm, cameraFilm);
            renderer.render(scenePoussiere, cameraPoussiere);
        },

        dimensionner,

        detruire() {
            texture.dispose();
            poussiere.geometry.dispose();
            if (poussiere.material.map) poussiere.material.map.dispose();
            poussiere.material.dispose();
            sceneFilm.traverse((o) => {
                if (o.geometry) o.geometry.dispose();
                if (o.material) o.material.dispose();
            });
            renderer.dispose();
        },
    };
}

/** Le repli sans WebGL : le même canvas, peint tel quel, recadré « cover ». */
function construireMonde2D(canvas, compositeur) {
    const ctx = canvas.getContext('2d', { alpha: false });
    if (!ctx) return null;

    let ratio = 1;

    function dimensionner() {
        ratio = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.max(1, Math.round((canvas.clientWidth || 1) * ratio));
        canvas.height = Math.max(1, Math.round((canvas.clientHeight || 1) * ratio));
    }

    dimensionner();

    return {
        moteur: '2d',
        rafraichirTexture() {},
        placer() {},

        rendre() {
            const l = canvas.width;
            const h = canvas.height;
            const ratioFilm = compositeur.largeur / compositeur.hauteur;
            const ratioVue = l / h;

            let dl = l;
            let dh = h;
            if (ratioVue > ratioFilm) dh = l / ratioFilm;
            else dl = h * ratioFilm;

            ctx.fillStyle = '#05070d';
            ctx.fillRect(0, 0, l, h);
            ctx.drawImage(compositeur.canvas, (l - dl) / 2, (h - dh) / 2, dl, dh);
        },

        dimensionner,
        detruire() {},
    };
}

/* ---------------------------------------------------------------------------
   Cycle de vie
   ------------------------------------------------------------------------ */
let etat = null;

export async function init() {
    const section = document.querySelector('[data-cx-film]');
    if (!section || section.dataset.cxFilmActif) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const canvas = section.querySelector('[data-cx-film-canvas]');
    if (!canvas) return;

    // Sans AVIF, on ne télécharge pas 350 images pour rien : le poster fait le travail.
    if (!(await avifDisponible())) return;

    let manifeste;
    try {
        manifeste = await chargerManifeste();
    } catch (e) {
        return; // manifeste absent -> poster + stepper
    }

    const connexion = navigator.connection || {};
    const petit = window.innerWidth < LARGEUR_MOBILE || connexion.saveData === true;
    const taille = petit ? 'mobile' : 'desktop';
    const [largeur, hauteur] = manifeste.tailles[taille];

    const magasin = creerMagasin(manifeste, taille);
    const compositeur = creerCompositeur(largeur, hauteur);

    // Rien ne s'affiche tant que la première image n'est pas décodée : sinon la
    // section clignoterait du noir au film sous les yeux du visiteur.
    const premiere = await magasin.amorcer();
    if (!premiere) {
        magasin.detruire();
        return;
    }

    // LA SCENE EST `display:none` TANT QUE `is-film` N'EST PAS POSEE : construire le moteur
    // avant cette ligne donnait un canvas de 0 px, donc un rendu en 1x1 etire plein ecran.
    // On ouvre la scene d'abord, on construit ensuite.
    section.dataset.cxFilmActif = '1';
    section.classList.add('is-film');

    const monde = construireMonde(canvas, compositeur) || construireMonde2D(canvas, compositeur);
    if (!monde) {
        delete section.dataset.cxFilmActif;
        section.classList.remove('is-film');
        magasin.detruire();
        return;
    }

    section.classList.add(monde.moteur === 'webgl' ? 'is-webgl' : 'is-2d');
    monde.dimensionner(); // la mise en page vient de changer : on remesure avant le premier rendu

    const chapitres = Array.prototype.slice.call(section.querySelectorAll('[data-cx-film-chapitre]'));
    const puces = Array.prototype.slice.call(section.querySelectorAll('[data-cx-film-puce]'));
    let chapitreCourant = -1;
    let position = 0;
    let raf = null;
    let vivant = true;

    function poserChapitre(index) {
        if (index === chapitreCourant) return;
        chapitreCourant = index;

        chapitres.forEach((c, k) => c.classList.toggle('is-active', k === index));
        puces.forEach((p, k) => p.classList.toggle('is-active', k === index));

        const actif = chapitres[index];
        if (actif) {
            gsap.fromTo(actif, { y: 16, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.5, ease: 'power2.out', overwrite: true });
        }
    }

    let premierRendu = false;

    function boucle(horodatage) {
        if (!vivant) return;

        const cadre = magasin.placer(position);
        if (compositeur.peindre(cadre)) monde.rafraichirTexture();

        monde.placer(position);
        monde.rendre(horodatage / 1000);

        // Le poster ne s'efface qu'APRES la premiere peinture reelle : sans cette garde,
        // un onglet dont le rAF ne tourne pas encore montrerait un rectangle noir.
        if (!premierRendu) {
            premierRendu = true;
            section.classList.add('is-peint');
        }

        // AVANCE DE 45 % D'UN PLAN : chaque plan PART de son image-clé et ARRIVE sur celle du
        // suivant. Sans cette avance, la légende décrivait encore le plan précédent pendant
        // que l'image montrait déjà le suivant.
        const avance = manifeste.parPlan * 0.45;
        poserChapitre(Math.min(manifeste.plans - 1, Math.floor((cadre.index + avance) / manifeste.parPlan)));

        raf = requestAnimationFrame(boucle);
    }

    const declencheur = ScrollTrigger.create({
        trigger: section.querySelector('[data-cx-film-piste]') || section,
        start: 'top top',
        end: 'bottom bottom',
        scrub: true,
        onUpdate(self) {
            position = self.progress;
            section.style.setProperty('--film-p', self.progress.toFixed(4));
        },
    });

    function surRedimensionnement() {
        monde.dimensionner();
    }

    window.addEventListener('resize', surRedimensionnement, { passive: true });

    poserChapitre(0);
    raf = requestAnimationFrame(boucle);
    ScrollTrigger.refresh();

    etat = {
        section,
        magasin,
        monde,
        declencheur,
        surRedimensionnement,
        pause() {
            vivant = false;
            if (raf != null) cancelAnimationFrame(raf);
            raf = null;
        },
        reprendre() {
            if (vivant) return;
            vivant = true;
            raf = requestAnimationFrame(boucle);
        },
        arreter() {
            this.pause();
        },
    };
}

export function teardown() {
    if (!etat) return;

    etat.arreter();
    window.removeEventListener('resize', etat.surRedimensionnement);
    etat.declencheur.kill();
    etat.monde.detruire();
    etat.magasin.detruire();

    delete etat.section.dataset.cxFilmActif;
    etat.section.classList.remove('is-film', 'is-webgl', 'is-2d', 'is-peint');

    etat = null;
}

// Onglet caché : on arrête de peindre. Une home ne doit pas chauffer une batterie.
document.addEventListener('visibilitychange', () => {
    if (!etat) return;
    if (document.hidden) etat.pause();
    else etat.reprendre();
});
