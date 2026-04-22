<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\RendezVous;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class FeedbackInviteController extends Controller
{
    public function create(RendezVous $rendezVous)
    {
        Gate::authorize('create', Feedback::class);

        if ($rendezVous->client_id !== Auth::id()) {
            abort(403);
        }

        return view('feedback.create', compact('rendezVous'));
    }

    public function store(Request $request, RendezVous $rendezVous)
    {
        Gate::authorize('create', Feedback::class);

        if ($rendezVous->client_id !== Auth::id()) {
            abort(403);
        }

        if ($rendezVous->feedback) {
            return redirect()->back()->with('error', 'Un feedback existe déjà pour ce rendez-vous.');
        }

        $validated = $request->validate([
            'note' => ['required', 'integer', 'min:1', 'max:5'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);

        Feedback::create([
            'client_id' => Auth::id(),
            'rendez_vous_id' => $rendezVous->id,
            'note' => $validated['note'],
            'commentaire' => $validated['commentaire'] ?? null,
        ]);

        return redirect()->route('client.dashboard')
            ->with('success', 'Feedback envoyé avec succès.');
    }
}