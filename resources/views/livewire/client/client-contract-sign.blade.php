<div class="py-6" x-data="signaturePadComponent()" x-init="initPad()">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Contrats</p>
            <h1 class="text-2xl font-black text-slate-900">Signature électronique</h1>
            <p class="text-sm text-slate-500">Signature eIDAS-lite : hash SHA256 du document + horodatage + IP + métadonnées.</p>
        </div>

        @if(! $this->currentDocument)
            {{-- Pending docs --}}
            <div class="rounded-2xl border bg-white shadow-sm p-5">
                <h2 class="text-lg font-bold text-slate-900 mb-3">Contrats en attente de signature</h2>
                <div class="space-y-2">
                    @forelse($this->pendingDocuments as $doc)
                        <button wire:click="selectDocument({{ $doc->id }})"
                                class="w-full text-left rounded-xl border bg-white hover:bg-indigo-50 p-3 flex items-center justify-between">
                            <div>
                                <p class="font-bold text-sm text-slate-900">{{ $doc->template?->name }}</p>
                                <p class="text-xs text-slate-500 font-mono">{{ $doc->code }}</p>
                            </div>
                            <span class="text-xs text-indigo-600 font-bold">Signer →</span>
                        </button>
                    @empty
                        <p class="text-sm text-slate-400">Aucun contrat en attente.</p>
                    @endforelse
                </div>
            </div>

            {{-- Templates disponibles --}}
            <div class="rounded-2xl border bg-white shadow-sm p-5">
                <h2 class="text-lg font-bold text-slate-900 mb-3">Modèles disponibles</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($this->availableTemplates as $tpl)
                        <div class="rounded-xl border p-3 flex items-start justify-between">
                            <div>
                                <p class="font-bold text-sm text-slate-900">{{ $tpl->name }}</p>
                                <p class="text-xs text-slate-500">{{ $tpl->description }}</p>
                                <p class="text-xs text-slate-400 mt-1 font-mono">{{ $tpl->code }} · v{{ $tpl->version }}</p>
                            </div>
                            <button wire:click="renderFromTemplate('{{ $tpl->code }}')"
                                    class="rounded-xl bg-indigo-600 text-white px-3 py-1.5 text-xs font-semibold hover:bg-indigo-700">
                                Générer
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Signed docs --}}
            @if($this->signedDocuments->isNotEmpty())
                <div class="rounded-2xl border bg-white shadow-sm p-5">
                    <h2 class="text-lg font-bold text-slate-900 mb-3">Contrats signés (10 derniers)</h2>
                    <ul class="space-y-1">
                        @foreach($this->signedDocuments as $doc)
                            <li class="flex items-center justify-between rounded-lg border p-2">
                                <div>
                                    <span class="text-sm">{{ $doc->template?->name }}</span>
                                    <span class="text-xs text-slate-500 font-mono ml-2">{{ $doc->code }}</span>
                                </div>
                                <a href="/api/v2/contracts/documents/{{ $doc->id }}/pdf" target="_blank"
                                   class="text-indigo-600 text-xs hover:underline">📄 PDF</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

        @else
            {{-- Signature view --}}
            <div class="rounded-2xl border bg-white shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-bold text-slate-900">{{ $this->currentDocument->template?->name }}</h2>
                    <button wire:click="$set('documentId', null)" class="text-slate-500 hover:text-slate-700 text-sm">
                        ← Retour
                    </button>
                </div>
                <p class="text-xs font-mono text-slate-500 mb-4">{{ $this->currentDocument->code }} · v{{ $this->currentDocument->template?->version }}</p>

                {{-- Body rendu HTML --}}
                <div class="rounded-xl border bg-slate-50 p-4 max-h-96 overflow-y-auto prose prose-sm">
                    {!! $this->currentDocument->body_rendered_html !!}
                </div>

                {{-- Signature pad --}}
                <div class="mt-6 space-y-3">
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Votre nom (signataire) *</label>
                        <input type="text" wire:model="signerName" maxlength="191"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                        @error('signerName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Pays (ISO 2 lettres)</label>
                        <input type="text" wire:model="countryCode" maxlength="2" placeholder="BE"
                               class="mt-1 w-24 rounded-xl border-gray-300 text-sm" />
                    </div>

                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Signature * (signez dans le cadre)</label>
                        <div class="mt-1 rounded-xl border-2 border-slate-300 bg-white">
                            <canvas x-ref="pad" class="w-full h-40 touch-none cursor-crosshair"></canvas>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <button type="button" @click="clearPad()" class="text-xs text-slate-500 hover:text-slate-900">↻ Effacer</button>
                            <span class="text-xs text-slate-400">Souris ou doigt</span>
                        </div>
                        @error('signatureData') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-start gap-2">
                        <input type="checkbox" wire:model="termsAccepted" class="mt-0.5 rounded border-gray-300" />
                        <span class="text-xs text-slate-700">
                            J'ai lu et j'accepte les termes du contrat ci-dessus. Je comprends que ma signature
                            électronique est juridiquement contraignante (eIDAS).
                        </span>
                    </label>
                    @error('termsAccepted') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror

                    <button @click="saveSignature(); $wire.sign();"
                            class="rounded-xl bg-emerald-600 text-white px-5 py-2.5 text-sm font-bold hover:bg-emerald-700">
                        Signer le contrat
                    </button>
                </div>
            </div>
        @endif
    </div>

    <script>
        function signaturePadComponent() {
            return {
                canvas: null,
                ctx: null,
                drawing: false,
                lastX: 0,
                lastY: 0,
                initPad() {
                    this.canvas = this.$refs.pad;
                    if (!this.canvas) return;
                    const rect = this.canvas.getBoundingClientRect();
                    this.canvas.width = rect.width;
                    this.canvas.height = rect.height;
                    this.ctx = this.canvas.getContext('2d');
                    this.ctx.strokeStyle = '#1e293b';
                    this.ctx.lineWidth = 2;
                    this.ctx.lineCap = 'round';
                    const start = (e) => {
                        this.drawing = true;
                        const p = this.pointer(e);
                        this.lastX = p.x; this.lastY = p.y;
                    };
                    const move = (e) => {
                        if (!this.drawing) return;
                        const p = this.pointer(e);
                        this.ctx.beginPath();
                        this.ctx.moveTo(this.lastX, this.lastY);
                        this.ctx.lineTo(p.x, p.y);
                        this.ctx.stroke();
                        this.lastX = p.x; this.lastY = p.y;
                    };
                    const end = () => { this.drawing = false; };
                    this.canvas.addEventListener('mousedown', start);
                    this.canvas.addEventListener('mousemove', move);
                    this.canvas.addEventListener('mouseup', end);
                    this.canvas.addEventListener('mouseleave', end);
                    this.canvas.addEventListener('touchstart', (e) => { e.preventDefault(); start(e); });
                    this.canvas.addEventListener('touchmove', (e) => { e.preventDefault(); move(e); });
                    this.canvas.addEventListener('touchend', (e) => { e.preventDefault(); end(); });
                },
                pointer(e) {
                    const rect = this.canvas.getBoundingClientRect();
                    if (e.touches && e.touches[0]) {
                        return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
                    }
                    return { x: e.clientX - rect.left, y: e.clientY - rect.top };
                },
                clearPad() {
                    if (!this.ctx) return;
                    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                    this.$wire.set('signatureData', '');
                },
                saveSignature() {
                    if (!this.canvas) return;
                    const dataURL = this.canvas.toDataURL('image/png');
                    this.$wire.set('signatureData', dataURL);
                },
            };
        }
    </script>
</div>
