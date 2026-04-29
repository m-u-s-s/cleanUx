<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
    <h3 class="text-lg font-semibold text-slate-900">Validation finale</h3>

    <form
        onsubmit="finishMissionWithCode(event, {{ $mission->id }})"
        enctype="multipart/form-data"
        class="space-y-3">
        <input
            type="text"
            name="code"
            class="w-full rounded-xl border-slate-300"
            placeholder="Code de fin donné par le client"
            required>

        <input
            type="file"
            name="photos_apres[]"
            accept="image/*"
            multiple
            class="w-full rounded-xl border border-slate-300 p-2">

        <button
            type="submit"
            class="rounded-xl bg-emerald-600 px-4 py-2 text-white">
            Terminer la mission
        </button>
    </form>
    @if($successMessage)
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ $successMessage }}
    </div>
    @endif

    <textarea
        wire:model.defer="comment"
        rows="4"
        placeholder="Commentaire final..."
        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none"></textarea>

    @error('comment')
    <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror

    <div class="grid gap-3 md:grid-cols-2">
        <button wire:click="satisfied" type="button" class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white">
            Satisfait
        </button>

        <button wire:click="problem" type="button" class="rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white">
            Problème
        </button>
    </div>
</div>

<script>
    async function finishMissionWithCode(event, missionId) {
        event.preventDefault();

        const formData = new FormData(event.target);

        if (navigator.geolocation) {
            await new Promise((resolve) => {
                navigator.geolocation.getCurrentPosition((position) => {
                    formData.append('lat', position.coords.latitude);
                    formData.append('lng', position.coords.longitude);
                    resolve();
                }, () => resolve(), {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                });
            });
        }

        const response = await fetch(`/missions/${missionId}/finish`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        });

        const result = await response.json();

        if (!result.ok) {
            alert('Code invalide ou clôture impossible.');
            return;
        }

        alert('Mission terminée avec succès.');
        window.location.reload();
    }
</script>