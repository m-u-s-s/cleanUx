<?php

namespace App\Services\Assistant\Tools\Implementations;

use App\Models\FinanceInvoice;
use App\Models\User;
use App\Services\Assistant\Tools\Contracts\AssistantTool;

/**
 * Phase 5.1 — Tool: récupère les détails d'une facture.
 *
 * Lecture seule → exécution immédiate.
 * Vérifie que l'utilisateur est lié à la facture (client direct ou même org).
 */
class GetInvoiceTool implements AssistantTool
{
    public function name(): string
    {
        return 'get_invoice';
    }

    public function description(): string
    {
        return "Récupère les détails d'une facture par son numéro ou son ID. "
            . "Utile pour répondre 'qu'est-ce que je dois payer', 'pourquoi ce montant', "
            . "'quand est due ma facture'. Retourne montant, statut, dates et détails.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'invoice_id' => [
                    'type'        => 'integer',
                    'description' => "ID interne de la facture.",
                ],
                'invoice_number' => [
                    'type'        => 'string',
                    'description' => "Numéro de facture (ex: 'INV-2026-0042'). Alternative à invoice_id.",
                ],
            ],
            'required' => [],
        ];
    }

    public function authorize(User $user): bool
    {
        return true; // ownership check fait dans execute()
    }

    public function executesImmediately(): bool
    {
        return true;
    }

    public function execute(User $user, array $input): array
    {
        $query = FinanceInvoice::query();

        if (! empty($input['invoice_id'])) {
            $query->where('id', (int) $input['invoice_id']);
        } elseif (! empty($input['invoice_number'])) {
            $query->where('invoice_number', $input['invoice_number']);
        } else {
            // Si aucun identifiant : retourner les 5 dernières factures du user
            return $this->listLatestForUser($user);
        }

        $invoice = $query->first();

        if (! $invoice) {
            return ['ok' => false, 'error' => "Facture introuvable."];
        }

        // Ownership : client direct ou même org
        $isOwner = (int) $invoice->client_id === (int) $user->id;
        $isOrgMember = $invoice->organization_account_id
            && $user->organization_account_id
            && (int) $invoice->organization_account_id === (int) $user->organization_account_id;
        $isAdmin = method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin();

        if (! $isOwner && ! $isOrgMember && ! $isAdmin) {
            return ['ok' => false, 'error' => "Vous n'avez pas accès à cette facture."];
        }

        return [
            'ok'      => true,
            'invoice' => $this->format($invoice),
        ];
    }

    private function listLatestForUser(User $user): array
    {
        $query = FinanceInvoice::query()
            ->orderByDesc('created_at')
            ->limit(5);

        if ($user->organization_account_id) {
            $query->where(function ($q) use ($user) {
                $q->where('client_id', $user->id)
                  ->orWhere('organization_account_id', $user->organization_account_id);
            });
        } else {
            $query->where('client_id', $user->id);
        }

        $invoices = $query->get();

        return [
            'ok'       => true,
            'count'    => $invoices->count(),
            'invoices' => $invoices->map(fn ($i) => $this->format($i))->all(),
        ];
    }

    private function format(FinanceInvoice $invoice): array
    {
        return [
            'id'             => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status'         => $invoice->status,
            'currency'       => $invoice->currency ?? 'EUR',
            'subtotal'       => (float) $invoice->subtotal,
            'tax_amount'     => (float) $invoice->tax_amount,
            'total_amount'   => (float) $invoice->total_amount,
            'balance_due'    => (float) $invoice->balance_due,
            'issued_at'      => $invoice->issued_at?->format('d/m/Y'),
            'due_at'         => $invoice->due_at?->format('d/m/Y'),
            'paid_at'        => $invoice->paid_at?->format('d/m/Y'),
            'is_paid'        => $invoice->paid_at !== null,
            'is_overdue'     => $invoice->due_at && $invoice->due_at->isPast() && $invoice->paid_at === null,
        ];
    }
}
