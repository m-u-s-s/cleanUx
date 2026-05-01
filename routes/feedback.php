<?php

use App\Models\Feedback;
use App\Models\RendezVous;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/feedback/{rendezVous}', function (RendezVous $rendezVous) {
        abort_unless($rendezVous->client_id === auth()->id(), 403);

        return view('feedback.create', [
            'rendezVous' => $rendezVous,
        ]);
    })->name('feedback.create');

    Route::post('/feedback/{rendezVous}', function (Request $request, RendezVous $rendezVous) {
        abort_unless($rendezVous->client_id === auth()->id(), 403);

        if (Feedback::where('rendez_vous_id', $rendezVous->id)->exists()) {
            return redirect()->route('feedback.create', $rendezVous)
                ->withErrors(['feedback' => 'Un feedback existe déjà pour ce rendez-vous.']);
        }

        $validated = $request->validate([
            'note' => ['required', 'integer', 'min:1', 'max:5'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        Feedback::create([
            'client_id' => auth()->id(),
            'rendez_vous_id' => $rendezVous->id,
            'note' => $validated['note'],
            'commentaire' => $validated['commentaire'] ?? null,
        ]);

        return redirect()->route('client.dashboard')
            ->with('success', 'Merci pour votre feedback.');
    })->name('feedback.store');
});