{{--
    LA CARTE DU TRAJET — départ, arrivée, et la route entre les deux.

    ELLE COMPLÈTE LA SAISIE, ELLE NE LA REMPLACE PAS. Taper une adresse reste plus rapide, et reste
    le seul chemin utilisable au clavier ou au lecteur d'écran : les champs des deux questions
    demeurent intacts au-dessus. Ce que la carte apporte, c'est la PRÉCISION — « Rue de la Loi 1 »
    désigne un bâtiment, pas la porte de service ni le côté du terre-plein où le conducteur doit
    s'arrêter. Sur une course, ces trente mètres décident si le client trouve sa voiture.

    ELLE NE S'AFFICHE QUE SUR UN TRAJET, et seulement quand au moins un point est connu : une carte
    du néant centrée sur un pays entier n'apprend rien et occupe le premier écran.

    UN CLIC A UNE CIBLE EXPLICITE. Deux onglets disent lequel des deux points sera déplacé. Sans
    eux, un appui sur la carte devrait deviner — et devinerait mal une fois sur deux, sur le seul
    écran où se déplacer de trente mètres coûte de l'argent.

    LE MODE DÉGRADÉ EST SILENCIEUX. Si Leaflet ne se charge pas — réseau filtré, bloqueur, tuiles
    inaccessibles — le bloc reste un cadre vide et la commande continue par les champs. Une carte
    est un confort ; en faire une dépendance bloquerait une réservation pour un CDN indisponible.
--}}
@php
    $depart = ($lat !== null && $lng !== null) ? ['lat' => (float) $lat, 'lng' => (float) $lng] : null;
    $brouillon = $this->draft();
    $arrivee = ($brouillon->dropoff_lat !== null && $brouillon->dropoff_lng !== null)
        ? ['lat' => (float) $brouillon->dropoff_lat, 'lng' => (float) $brouillon->dropoff_lng]
        : null;
    $trace = $this->pointsDeLaRoute;
    $itineraire = $this->route;
@endphp

@if ($this->estUnTrajet && ($depart || $arrivee))
    <section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="carte-titre">
        <div class="flex items-baseline justify-between gap-3">
            <h2 id="carte-titre" class="text-lg font-semibold text-slate-900">Le trajet</h2>

            @if ($itineraire)
                <p class="text-sm tabular-nums text-slate-500">
                    {{ number_format($itineraire['distance_km'], 1, ',', ' ') }} km
                    @if ($itineraire['duration_min'])
                        · {{ $itineraire['approximatif'] ? '~' : '' }}{{ $itineraire['duration_min'] }} min
                    @endif
                </p>
            @endif
        </div>

        <div
            x-data="carteDuTrajet(@js([
                'depart' => $depart,
                'arrivee' => $arrivee,
                'trace' => $trace,
                'approximatif' => (bool) ($itineraire['approximatif'] ?? true),
            ]))"
            x-init="boot()"
            wire:ignore
            class="mt-4"
        >
            {{-- Les deux onglets : ils disent ce qu'un appui sur la carte va déplacer. --}}
            <div class="flex gap-2" role="radiogroup" aria-label="Point à ajuster sur la carte">
                <template x-for="role in ['pickup', 'dropoff']" :key="role">
                    <button type="button"
                            :aria-label="role === 'pickup' ? 'Point de départ' : 'Point d’arrivée'"
                        role="radio"
                        :aria-checked="cible === role ? 'true' : 'false'"
                        x-on:click="cible = role"
                        :class="cible === role
                            ? 'border-slate-900 bg-slate-900 text-white'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                        class="inline-flex min-h-[36px] items-center gap-2 rounded-full border px-4 text-sm font-medium transition">
                        <span aria-hidden="true"
                            class="grid h-5 w-5 place-items-center rounded-full text-[11px] font-semibold"
                            :class="cible === role ? 'bg-white text-slate-900' : 'bg-slate-100 text-slate-600'"
                            x-text="role === 'pickup' ? 'A' : 'B'"></span>
                        <span x-text="role === 'pickup' ? 'Départ' : 'Arrivée'"></span>
                    </button>
                </template>
            </div>

            {{--
                `wire:ignore` sur le conteneur parent, et le div de carte reste hors du cycle de
                rendu de Livewire : sans cela, chaque réponse remplacerait le nœud et Leaflet
                refuserait de réinitialiser un conteneur qu'il croit déjà occupé.
            --}}
            <div id="carte-trajet"
                class="mt-3 h-[260px] w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-100 sm:h-[320px]"
                role="application"
                aria-label="Carte du trajet. Les adresses se saisissent aussi dans les champs ci-dessus."></div>

            <p class="mt-2 text-xs text-slate-500">
                Glissez un repère, ou touchez la carte pour poser le point sélectionné.
                @if (($itineraire['approximatif'] ?? false))
                    Le tracé est approximatif tant qu’aucun itinéraire routier n’est disponible.
                @endif
            </p>
        </div>
    </section>

    @push('scripts')
        {{--
            Le même Leaflet que l'écran de recherche immédiate. Les deux ne coexistent jamais sur
            une même page — un parcours de commande n'affiche pas la recherche en cours — mais la
            fonction se garde de toute façon d'un double chargement.
        --}}
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            window.carteDuTrajet = (cfg) => ({
                map: null,
                reperes: { pickup: null, dropoff: null },
                trace: null,
                cible: cfg.depart ? 'dropoff' : 'pickup',

                boot() {
                    if (typeof L === 'undefined') {
                        return;
                    }

                    const conteneur = document.getElementById('carte-trajet');

                    if (! conteneur) {
                        return;
                    }

                    // Leaflet refuse d'initialiser deux fois le même nœud ; la navigation Livewire
                    // peut nous ramener ici avec un conteneur déjà marqué.
                    if (conteneur._leaflet_id) {
                        conteneur._leaflet_id = null;
                    }

                    this.map = L.map(conteneur, {
                        zoomControl: true,
                        attributionControl: false,
                    }).setView([cfg.depart?.lat ?? cfg.arrivee.lat, cfg.depart?.lng ?? cfg.arrivee.lng], 13);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this.map);

                    this.poser('pickup', cfg.depart);
                    this.poser('dropoff', cfg.arrivee);
                    this.tracer(cfg.trace, cfg.approximatif);
                    this.cadrer();

                    // Un appui sur la carte pose le point SÉLECTIONNÉ — jamais un point deviné.
                    this.map.on('click', (e) => this.annoncer(this.cible, e.latlng.lat, e.latlng.lng));

                    /*
                     * LA CARTE N'APPREND RIEN DU RENDU : elle vit sous `wire:ignore`, Leaflet
                     * possède son nœud. C'est le serveur qui annonce les points, par un événement,
                     * chaque fois que l'un d'eux bouge — y compris quand le client corrige son
                     * adresse au clavier dans le champ voisin.
                     */
                    Livewire.on('trajet-mis-a-jour', (charge) => {
                        const donnees = Array.isArray(charge) ? charge[0] : charge;
                        this.resynchroniser(donnees?.trajet ?? donnees);
                    });
                },

                poser(role, point) {
                    if (! point) {
                        return;
                    }

                    const lettre = role === 'pickup' ? 'A' : 'B';

                    const icone = L.divIcon({
                        className: '',
                        html: `<span style="display:grid;place-items:center;width:28px;height:28px;border-radius:9999px;background:var(--brio-ink);color:#fff;font:600 12px/1 ui-sans-serif,system-ui;box-shadow:0 2px 8px rgb(var(--brio-ink-rgb) / .35)">${lettre}</span>`,
                        iconSize: [28, 28],
                        iconAnchor: [14, 14],
                    });

                    this.reperes[role] = L.marker([point.lat, point.lng], { icon: icone, draggable: true })
                        .addTo(this.map)
                        .on('dragend', (e) => {
                            const p = e.target.getLatLng();
                            this.annoncer(role, p.lat, p.lng);
                        });
                },

                tracer(points, approximatif) {
                    if (this.trace) {
                        this.map.removeLayer(this.trace);
                        this.trace = null;
                    }

                    if (! points || points.length < 2) {
                        return;
                    }

                    this.trace = L.polyline(points.map((p) => [p.lat, p.lng]), {
                        color: window.brioJeton('--brio-ink', '#0f172a'),
                        weight: 3,
                        opacity: 0.8,
                        // Le pointillé DIT que c'est une ligne droite faute d'itinéraire : un trait
                        // plein annoncerait un trajet routier que personne n'a calculé.
                        dashArray: approximatif ? '4 6' : null,
                    }).addTo(this.map);
                },

                cadrer() {
                    const poses = Object.values(this.reperes).filter(Boolean);

                    if (poses.length === 2) {
                        this.map.fitBounds(L.latLngBounds(poses.map((m) => m.getLatLng())), { padding: [32, 32] });
                    } else if (poses.length === 1) {
                        this.map.setView(poses[0].getLatLng(), 15);
                    }
                },

                annoncer(role, lat, lng) {
                    this.$wire.placerSurLaCarte(role, lat, lng);
                },

                resynchroniser(frais) {
                    if (! this.map || ! frais) {
                        return;
                    }

                    for (const role of ['pickup', 'dropoff']) {
                        const point = frais[role === 'pickup' ? 'depart' : 'arrivee'];

                        if (! point) {
                            continue;
                        }

                        if (this.reperes[role]) {
                            this.reperes[role].setLatLng([point.lat, point.lng]);
                        } else {
                            this.poser(role, point);
                        }
                    }

                    this.tracer(frais.trace, frais.approximatif);
                    this.cadrer();
                },
            });
        </script>
    @endpush
@endif
