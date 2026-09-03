{{--
    LE CONTRÔLE FACIAL, VERSION WEB.

    Même direction que le mobile : un fond profond, une seule chose à faire, un cercle de visée. La
    différence est le contexte — ici on est dans le tableau de bord, pas dans une pile vide — donc
    la carte porte elle-même sa gravité au lieu de compter sur l'absence de navigation.

    La capture passe par `getUserMedia` et un canevas, puis remonte par le téléversement Livewire.
    Aucune image ne transite par un service tiers depuis le navigateur : c'est le serveur qui parle
    au fournisseur, jamais la page.

    TOUS LES TEXTES VIENNENT DE `lang/<code>/face_check.php`. Le texte de consentement en
    particulier : c'est le seul du module qui engage juridiquement, et l'API sert EXACTEMENT le
    même à l'application mobile.
--}}
<div class="mx-auto max-w-3xl space-y-6 py-8" wire:key="face-check-page">

    {{-- LA PAGE DIT SON NOM. Sans h1, un lecteur d ecran commence par le menu, identique
         d un ecran a l autre : la coquille du depot le porte pour toutes les autres. --}}
    <x-page-shell
        :eyebrow="__('face_check.screen.eyebrow_check')"
        :title="__('face_check.screen.title_check')"
        :subtitle="__('face_check.screen.help_check')" />

    @if(! $soumis)
        <x-app-card :title="__('face_check.screen.eyebrow_check')">
            <p class="text-sm text-slate-600">{{ __('face_check.screen.not_concerned') }}</p>
        </x-app-card>
    @elseif($verdict->code === \App\Services\FaceCheck\Data\FaceCheckDecision::BLOCKED)

        <x-app-card>
            <div class="text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-rose-50 text-3xl">⛔</div>
                <h2 class="mt-4 text-2xl font-black tracking-tight text-slate-900">{{ __('face_check.screen.blocked_title') }}</h2>
                <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-600">{{ $verdict->message }}</p>
                <p class="mx-auto mt-4 max-w-lg text-xs leading-5 text-slate-400">{{ __('face_check.screen.blocked_note') }}</p>
            </div>
        </x-app-card>

        @include('livewire.provider.partials.face-check-incident')

    @elseif($verdict->code === \App\Services\FaceCheck\Data\FaceCheckDecision::CHECK_PENDING)

        <x-app-card>
            <div class="text-center">
                <h2 class="text-2xl font-black tracking-tight text-slate-900">{{ __('face_check.screen.pending_title') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ __('face_check.screen.pending_body') }}</p>
                <div class="mt-6">
                    <button type="button" wire:click="rafraichir" class="brio-btn-primary">{{ __('face_check.screen.refresh') }}</button>
                </div>
            </div>
        </x-app-card>

    @elseif($verdict->allowed())

        <x-app-card>
            <div class="text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-3xl">✅</div>
                <h2 class="mt-4 text-2xl font-black tracking-tight text-slate-900">{{ __('face_check.screen.all_good_title') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ __('face_check.screen.all_good_body') }}</p>
                @if($profil?->captured_at)
                    <p class="mt-3 text-xs text-slate-400">
                        {{ __('face_check.screen.enrolled_since', ['when' => $profil->captured_at->diffForHumans()]) }}
                        · {{ __('face_check.consent.version_label', ['version' => $profil->consent_version]) }}
                    </p>
                @endif
            </div>
        </x-app-card>

    @else

        @php $enrolement = $verdict->code === \App\Services\FaceCheck\Data\FaceCheckDecision::ENROLMENT_REQUIRED; @endphp

        <x-app-card padding="p-0">
            <div class="overflow-hidden rounded-[20px] bg-slate-950">
                {{-- La visée : un cercle, une vidéo, rien d'autre. --}}
                <div class="relative aspect-[4/3] w-full bg-black" x-data="captureFaciale()" x-init="demarrer()">
                    <video x-ref="video" autoplay playsinline muted class="h-full w-full object-cover"></video>
                    <canvas x-ref="canvas" class="hidden"></canvas>

                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="h-[68%] w-[52%] rounded-[50%] border-2 border-white/60 shadow-[0_0_40px_rgba(255,255,255,0.25)]"></div>
                    </div>

                    <div class="absolute inset-x-0 bottom-0 flex flex-col gap-3 bg-gradient-to-t from-slate-950 via-slate-950/80 to-transparent p-5">
                        <p x-show="erreur" x-text="erreur" class="text-sm font-semibold text-rose-300"></p>

                        <button type="button"
                                x-on:click="capturer()"
                                x-bind:disabled="occupe"
                                class="w-full rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100 disabled:opacity-50">
                            <span x-show="!occupe">{{ $enrolement ? __('face_check.screen.capture_enrolment') : __('face_check.screen.capture_check') }}</span>
                            <span x-show="occupe">{{ __('face_check.incident.send') }}…</span>
                        </button>
                    </div>
                </div>

                <div class="space-y-3 p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.15em] text-slate-400">
                        {{ $enrolement ? __('face_check.screen.eyebrow_enrolment') : __('face_check.screen.eyebrow_check') }}
                    </p>
                    <h2 class="text-xl font-black tracking-tight text-white">
                        {{ $enrolement ? __('face_check.screen.title_enrolment') : __('face_check.screen.title_check') }}
                    </h2>
                    <p class="text-sm leading-6 text-slate-300">
                        {{ $enrolement ? __('face_check.screen.help_enrolment') : __('face_check.screen.help_check') }}
                    </p>

                    @if($vivaciteExigee)
                        <p class="text-xs leading-5 text-slate-400">{{ __('face_check.screen.liveness_hint') }}</p>
                    @endif

                    @if($enrolement)
                        <label class="mt-2 flex items-start gap-3 rounded-2xl bg-white/5 p-4">
                            <input type="checkbox" wire:model="consentement" class="mt-0.5 rounded text-emerald-500" />
                            <span class="text-xs leading-5 text-slate-300">
                                {{ $texteDuConsentement }}
                                <span class="mt-1 block text-slate-500">
                                    {{ $noteJuridique }}
                                    {{ __('face_check.consent.version_label', ['version' => $versionDuConsentement]) }}
                                </span>
                            </span>
                        </label>
                    @endif

                    @error('selfie') <p class="text-xs text-rose-300">{{ $message }}</p> @enderror
                    @error('consentement') <p class="text-xs text-rose-300">{{ $message }}</p> @enderror

                    @if($controle && $controle->attempt_number > 1)
                        <p class="text-xs text-amber-300">
                            {{ __('face_check.screen.attempt_recap', ['number' => $controle->attempt_number, 'reason' => $controle->failure_reason]) }}
                        </p>
                    @endif
                </div>
            </div>
        </x-app-card>

        @include('livewire.provider.partials.face-check-incident')

        @push('scripts')
            <script>
                function captureFaciale() {
                    return {
                        occupe: false,
                        erreur: null,
                        flux: null,

                        async demarrer() {
                            try {
                                // `facingMode: user` : la caméra frontale. Sur un poste fixe, la seule.
                                this.flux = await navigator.mediaDevices.getUserMedia({
                                    video: { facingMode: 'user', width: { ideal: 1280 } },
                                    audio: false,
                                });
                                this.$refs.video.srcObject = this.flux;
                            } catch (e) {
                                this.erreur = @js(__('face_check.camera.unavailable'));
                            }
                        },

                        destroy() {
                            // La caméra se coupe en quittant la page : une diode allumée sans raison
                            // est le genre de détail qui fait désinstaller une application.
                            this.flux?.getTracks().forEach((piste) => piste.stop());
                        },

                        async capturer() {
                            if (this.occupe || !this.flux) {
                                return;
                            }

                            this.occupe = true;
                            this.erreur = null;

                            try {
                                const video = this.$refs.video;
                                const canvas = this.$refs.canvas;

                                canvas.width = video.videoWidth;
                                canvas.height = video.videoHeight;
                                canvas.getContext('2d').drawImage(video, 0, 0);

                                const blob = await new Promise((resolve) =>
                                    canvas.toBlob(resolve, 'image/jpeg', 0.8)
                                );

                                const fichier = new File([blob], 'selfie.jpg', { type: 'image/jpeg' });

                                await new Promise((resolve, reject) => {
                                    @this.upload('selfie', fichier, resolve, reject);
                                });

                                await @this.call('{{ $enrolement ? 'enregistrerLeVisage' : 'envoyerLeSelfie' }}');
                            } catch (e) {
                                this.erreur = @js(__('face_check.result.upload_failed'));
                            } finally {
                                this.occupe = false;
                            }
                        },
                    };
                }
            </script>
        @endpush
    @endif
</div>
