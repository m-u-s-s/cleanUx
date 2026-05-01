<x-app-layout>
    <div class="mx-auto max-w-2xl px-4 py-8">
        <div class="rounded-3xl border bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-black text-slate-900">Laisser un feedback</h1>

            <form method="POST" action="{{ route('feedback.store', $rendezVous) }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Note</label>
                    <select name="note" class="mt-1 w-full rounded-xl border-slate-300">
                        <option value="5">5 - Excellent</option>
                        <option value="4">4 - Très bien</option>
                        <option value="3">3 - Correct</option>
                        <option value="2">2 - Moyen</option>
                        <option value="1">1 - Mauvais</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Commentaire</label>
                    <textarea name="commentaire" rows="4" class="mt-1 w-full rounded-xl border-slate-300"></textarea>
                </div>

                <button class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white">
                    Envoyer
                </button>
            </form>
        </div>
    </div>
</x-app-layout>