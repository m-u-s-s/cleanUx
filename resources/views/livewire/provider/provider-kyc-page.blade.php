<div class="py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Vérification d'identité</p>
            <h1 class="text-3xl font-black text-slate-900">KYC — Background check</h1>
            <p class="text-sm text-slate-500 mt-2">
                Validation requise pour accepter des missions. Vérification automatique en quelques minutes.
            </p>
        </div>

        @if($errorMessage)
            <div class="rounded-2xl bg-red-50 border border-red-200 p-4 text-sm text-red-700">
                {{ $errorMessage }}
            </div>
        @endif
        @if($successMessage)
            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-700">
                {{ $successMessage }}
            </div>
        @endif

        {{-- Status card --}}
        @if($verification)
            <div @class([
                'rounded-3xl border p-6 shadow-sm',
                'bg-emerald-50 border-emerald-200' => $verification->decision === 'approved',
                'bg-amber-50 border-amber-200' => in_array($verification->decision, ['pending','manual_review']),
                'bg-red-50 border-red-200' => $verification->decision === 'rejected',
            ])>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase font-bold {{ $verification->decision === 'approved' ? 'text-emerald-700' : ($verification->decision === 'rejected' ? 'text-red-700' : 'text-amber-700') }}">
                            État
                        </p>
                        <h2 class="text-2xl font-black mt-1">
                            @switch($verification->decision)
                                @case('approved') ✓ Vérifié @break
                                @case('rejected') ✕ Rejeté @break
                                @case('manual_review') ⌛ Review manuel en cours @break
                                @default ⌛ En cours @break
                            @endswitch
                        </h2>
                        <p class="text-sm text-slate-700 mt-1">
                            Provider : <span class="font-mono">{{ $verification->provider }}</span>
                            @if($verification->score !== null)
                                · Score : <span class="font-bold">{{ number_format((float) $verification->score, 2) }}</span>
                            @endif
                        </p>
                        @if($verification->rejection_reason)
                            <p class="text-sm text-red-700 mt-3 italic">{{ $verification->rejection_reason }}</p>
                        @endif
                    </div>

                    @if(! in_array($verification->status, ['clear', 'rejected', 'cancelled']))
                        <button wire:click="sync({{ $verification->id }})"
                                class="rounded-xl bg-white border px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">
                            Rafraîchir
                        </button>
                    @endif
                </div>

                @if(data_get($verification->metadata, 'hosted_flow_url'))
                    <a href="{{ data_get($verification->metadata, 'hosted_flow_url') }}"
                       target="_blank" rel="noopener"
                       class="mt-4 inline-block rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Compléter la vérification →
                    </a>
                @endif

                @if($verification->checks->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-current/10">
                        <p class="text-xs uppercase font-bold mb-2">Détail des checks</p>
                        @foreach($verification->checks as $check)
                            <div class="flex items-center justify-between py-1 text-sm">
                                <span class="font-semibold">{{ $check->check_type }}</span>
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $check->result === 'clear',
                                    'bg-amber-100 text-amber-800' => $check->result === 'consider',
                                    'bg-red-100 text-red-800' => $check->result === 'rejected',
                                    'bg-slate-100 text-slate-700' => in_array($check->result, ['pending','unidentified','caution']),
                                ])>{{ $check->result }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if($verification->decision === 'rejected')
                <button wire:click="start"
                        class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-700">
                    Relancer une vérification
                </button>
            @endif
        @else
            <div class="rounded-3xl border-2 border-dashed border-slate-200 p-8 text-center">
                <p class="text-slate-600 mb-4">
                    Vous n'avez pas encore démarré votre vérification d'identité.
                </p>
                <p class="text-xs text-slate-500 mb-6">
                    Cela prend généralement quelques minutes : photo de carte d'identité + selfie.
                </p>
                <button wire:click="start"
                        class="rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-700">
                    Démarrer la vérification
                </button>
            </div>
        @endif

        @if($profile && $profile->verification_status === 'verified')
            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-700">
                ✓ Votre profil prestataire est marqué comme vérifié et peut accepter des missions.
            </div>
        @endif
    </div>
</div>
