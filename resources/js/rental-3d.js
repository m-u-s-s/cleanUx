/*
 * LE VISUALISEUR 3D DES VÉHICULES DE LOCATION.
 *
 * ── POURQUOI CE FICHIER EXISTE PLUTÔT QU'UN `x-data` INLINE ──────────────────────────────────
 *
 * Le code tient dans l'attribut d'une balise, techniquement. Mais `import()` dans une chaîne Blade
 * échappe à Vite : le bundler ne voit pas la dépendance, ne découpe rien, et three.js finit soit
 * absent en production, soit chargé en entier sur chaque page. Ici, Vite reconnaît les imports
 * dynamiques et produit un fragment séparé.
 *
 * ── TROIS RÈGLES QUE CE DÉPÔT A DÉJÀ PAYÉES ──────────────────────────────────────────────────
 *
 * THREE.JS N'ARRIVE QUE SI LA VUE ARRIVE À L'ÉCRAN. Le catalogue peut montrer vingt voitures ;
 * charger le moteur 3D pour chacune ferait payer des mégaoctets à qui n'ouvre aucune fiche.
 *
 * L'ÉCHEC RETOMBE SUR LA PHOTO. Pas de WebGL, fichier corrompu, mémoire insuffisante sur un vieux
 * mobile : la fiche reste utilisable et le client peut réserver. Une erreur visible à sa place
 * ferait croire que la voiture est indisponible.
 *
 * LE MOUVEMENT RÉDUIT EST RESPECTÉ. La rotation automatique s'arrête quand le système la refuse.
 */
function modele3dLocation(url) {
    return {
        monte: false,
        echec: false,
        nettoyer: null,

        init() {
            const observateur = new IntersectionObserver(
                (entrees) => {
                    if (!entrees[0].isIntersecting || this.monte) {
                        return
                    }

                    observateur.disconnect()
                    this.monte = true

                    this.monterLaScene().catch(() => {
                        this.echec = true
                    })
                },
                { rootMargin: '200px' },
            )

            observateur.observe(this.$el)

            // Livewire remplace le DOM sans prévenir : sans démontage, chaque navigation laisse un
            // contexte WebGL vivant, et le navigateur en refuse le seizième.
            this.$el.addEventListener('livewire:navigating', () => this.nettoyer?.(), { once: true })
        },

        async monterLaScene() {
            const THREE = await import('three')
            const { GLTFLoader } = await import('three/examples/jsm/loaders/GLTFLoader.js')
            const { OrbitControls } = await import('three/examples/jsm/controls/OrbitControls.js')

            const hote = this.$refs.toile
            const scene = new THREE.Scene()
            const camera = new THREE.PerspectiveCamera(45, hote.clientWidth / hote.clientHeight, 0.1, 1000)

            const rendu = new THREE.WebGLRenderer({ antialias: true, alpha: true })
            rendu.setSize(hote.clientWidth, hote.clientHeight)
            rendu.setPixelRatio(Math.min(window.devicePixelRatio, 2))
            hote.appendChild(rendu.domElement)

            scene.add(new THREE.HemisphereLight(0xffffff, 0x444444, 1.2))
            const directionnelle = new THREE.DirectionalLight(0xffffff, 1.0)
            directionnelle.position.set(5, 10, 7)
            scene.add(directionnelle)

            const gltf = await new GLTFLoader().loadAsync(url)
            scene.add(gltf.scene)

            /*
             * ON CADRE SUR LA BOÎTE ENGLOBANTE DU MODÈLE, pas sur une distance fixe.
             *
             * Une voiture modélisée en centimètres et une autre en mètres n'ont pas la même échelle
             * dans le fichier. Une caméra placée à une distance constante montrerait l'une de loin
             * et l'intérieur de l'autre — et l'administrateur n'aurait aucun moyen de comprendre
             * pourquoi son modèle « ne marche pas ».
             */
            const boite = new THREE.Box3().setFromObject(gltf.scene)
            const diagonale = boite.getSize(new THREE.Vector3()).length()
            const centre = boite.getCenter(new THREE.Vector3())
            gltf.scene.position.sub(centre)
            camera.position.set(0, diagonale * 0.25, diagonale * 0.9)

            const controles = new OrbitControls(camera, rendu.domElement)
            controles.enableDamping = true
            controles.enablePan = false
            controles.minDistance = diagonale * 0.5
            controles.maxDistance = diagonale * 1.6
            controles.autoRotate = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
            controles.autoRotateSpeed = 0.8

            let image = 0
            const boucle = () => {
                image = requestAnimationFrame(boucle)
                controles.update()
                rendu.render(scene, camera)
            }
            boucle()

            const redimensionnement = new ResizeObserver(() => {
                if (hote.clientWidth === 0 || hote.clientHeight === 0) {
                    return
                }
                camera.aspect = hote.clientWidth / hote.clientHeight
                camera.updateProjectionMatrix()
                rendu.setSize(hote.clientWidth, hote.clientHeight)
            })
            redimensionnement.observe(hote)

            this.nettoyer = () => {
                cancelAnimationFrame(image)
                redimensionnement.disconnect()
                controles.dispose()
                rendu.dispose()
                rendu.domElement.remove()
            }
        },
    }
}

/*
 * ENREGISTREMENT AUPRÈS D'ALPINE, sur `alpine:init`.
 *
 * C'est la convention du dépôt (voir `client/feedback-form.blade.php`) : Alpine émet cet événement
 * avant de balayer le DOM. S'enregistrer plus tard laisserait un `x-data` introuvable et une fiche
 * silencieusement sans 3D.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('modele3dLocation', modele3dLocation)
})
