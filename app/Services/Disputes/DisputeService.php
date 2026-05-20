<?php

namespace App\Services\Disputes;

use App\Events\Disputes\DisputeEscalated;
use App\Events\Disputes\DisputeMessageAdded;
use App\Events\Disputes\DisputeOpened;
use App\Events\Disputes\DisputeStatusChanged;
use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Models\User;
use App\Notifications\Disputes\DisputeOpenedNotification;
use App\Notifications\Disputes\DisputeUpdatedNotification;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DisputeService
{
    public function __construct(
        protected DisputeSlaService $slaService,
        protected DisputeAutoResolver $autoResolver,
    ) {}

    /**
     * @param  array{subject:string, description:string, category:string, priority?:string, severity?:string, attachments?:array, booking_id?:int|null}  $data
     */
    public function open(User $client, array $data): ComplaintCase
    {
        $priority = $data['priority'] ?? ComplaintCase::PRIORITY_NORMAL;
        $severity = $data['severity'] ?? ComplaintCase::SEVERITY_MEDIUM;
        $category = $data['category'] ?? ComplaintCase::CATEGORY_OTHER;
        $booking = isset($data['booking_id']) ? Booking::find($data['booking_id']) : null;

        if ($booking && (int) $booking->client_id !== (int) $client->id) {
            throw ValidationException::withMessages([
                'booking_id' => "Ce booking ne vous appartient pas.",
            ]);
        }

        $due = $this->slaService->computeDueAt($priority, $severity);

        return DB::transaction(function () use ($client, $data, $priority, $severity, $category, $booking, $due) {
            $case = ComplaintCase::create([
                'reference' => $this->generateReference(),
                'client_id' => $client->id,
                'rendez_vous_id' => $booking?->id,
                'booking_id' => $booking?->id,
                'provider_user_id' => $booking?->employe_id,
                'organization_account_id' => $booking?->organization_account_id ?? $client->organization_account_id,
                'category' => $category,
                'priority' => $priority,
                'severity' => $severity,
                'sla_policy' => $this->slaService->slaPolicyLabel($priority, $severity),
                'status' => ComplaintCase::STATUS_OPEN,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'attachments' => $data['attachments'] ?? [],
                'due_at' => $due,
                'last_activity_at' => now(),
            ]);

            $this->recordEvent($case, DisputeEvent::TYPE_OPENED, [
                'author_user_id' => $client->id,
                'author_role' => DisputeEvent::ROLE_CLIENT,
                'body' => $case->description,
                'visibility' => DisputeEvent::VISIBILITY_ALL,
            ]);

            ActivityLogger::log('dispute.opened', $case, [
                'client_id' => $client->id,
                'category' => $category,
                'severity' => $severity,
            ]);

            DisputeOpened::dispatch($case);

            $this->notifyOpened($case);
            $this->autoResolver->maybeAutoResolve($case);

            \App\Support\Webhooks\BusinessEventEmitter::emit(
                eventCode: 'dispute.opened',
                payload: [
                    'dispute_id' => $case->id,
                    'reference' => $case->reference,
                    'client_id' => $case->client_id,
                    'booking_id' => $case->booking_id,
                    'provider_user_id' => $case->provider_user_id,
                    'category' => $case->category,
                    'priority' => $case->priority,
                    'severity' => $case->severity,
                    'subject' => $case->subject,
                ],
                idempotencyKey: 'dispute.opened:' . $case->id,
                sourceType: ComplaintCase::class,
                sourceId: (int) $case->id,
            );

            return $case->fresh();
        });
    }

    public function addMessage(
        ComplaintCase $case,
        User $author,
        string $role,
        string $body,
        string $visibility = DisputeEvent::VISIBILITY_ALL,
        array $attachments = [],
    ): DisputeEvent {
        if ($case->isFinal()) {
            throw ValidationException::withMessages([
                'body' => "Cette dispute est clôturée, vous ne pouvez plus y répondre.",
            ]);
        }

        $type = match ($role) {
            DisputeEvent::ROLE_ADMIN => DisputeEvent::TYPE_ADMIN_MESSAGE,
            DisputeEvent::ROLE_PROVIDER => DisputeEvent::TYPE_PROVIDER_RESPONSE,
            default => DisputeEvent::TYPE_MESSAGE,
        };

        $event = $this->recordEvent($case, $type, [
            'author_user_id' => $author->id,
            'author_role' => $role,
            'body' => $body,
            'attachments' => $attachments,
            'visibility' => $visibility,
        ]);

        $updates = ['last_activity_at' => now()];
        if (! $case->first_response_at && $role === DisputeEvent::ROLE_ADMIN) {
            $updates['first_response_at'] = now();
        }

        // Auto-status transitions
        if ($role === DisputeEvent::ROLE_CLIENT && $case->status === ComplaintCase::STATUS_AWAITING_CLIENT) {
            $updates['status'] = ComplaintCase::STATUS_INVESTIGATING;
        }
        if ($role === DisputeEvent::ROLE_PROVIDER && $case->status === ComplaintCase::STATUS_AWAITING_PROVIDER) {
            $updates['status'] = ComplaintCase::STATUS_INVESTIGATING;
        }

        $case->update($updates);

        DisputeMessageAdded::dispatch($event);
        $this->notifyUpdate($case, $author);

        return $event;
    }

    public function transition(ComplaintCase $case, string $newStatus, ?User $actor = null, ?string $note = null): ComplaintCase
    {
        $oldStatus = $case->status;
        if ($oldStatus === $newStatus) {
            return $case;
        }

        $case->update([
            'status' => $newStatus,
            'last_activity_at' => now(),
            'resolved_at' => $newStatus === ComplaintCase::STATUS_RESOLVED ? now() : $case->resolved_at,
            'closed_at' => $newStatus === ComplaintCase::STATUS_CLOSED ? now() : $case->closed_at,
        ]);

        $this->recordEvent($case, DisputeEvent::TYPE_STATUS_CHANGED, [
            'author_user_id' => $actor?->id,
            'author_role' => $actor ? $this->roleOf($actor, $case) : DisputeEvent::ROLE_SYSTEM,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'body' => $note,
            'visibility' => DisputeEvent::VISIBILITY_ALL,
        ]);

        DisputeStatusChanged::dispatch($case, $oldStatus, $newStatus);
        $this->notifyUpdate($case, $actor);

        if (in_array($newStatus, [ComplaintCase::STATUS_RESOLVED, ComplaintCase::STATUS_CLOSED], true)) {
            \App\Support\Webhooks\BusinessEventEmitter::emit(
                eventCode: 'dispute.resolved',
                payload: [
                    'dispute_id' => $case->id,
                    'reference' => $case->reference,
                    'from_status' => $oldStatus,
                    'to_status' => $newStatus,
                    'client_id' => $case->client_id,
                    'booking_id' => $case->booking_id,
                ],
                idempotencyKey: 'dispute.resolved:' . $case->id . ':' . $newStatus,
                sourceType: ComplaintCase::class,
                sourceId: (int) $case->id,
            );
        }

        return $case->fresh();
    }

    public function assign(ComplaintCase $case, User $admin): ComplaintCase
    {
        $case->update([
            'assigned_to' => $admin->id,
            'status' => $case->status === ComplaintCase::STATUS_OPEN
                ? ComplaintCase::STATUS_ASSIGNED
                : $case->status,
            'last_activity_at' => now(),
        ]);

        $this->recordEvent($case, DisputeEvent::TYPE_ASSIGNED, [
            'author_user_id' => $admin->id,
            'author_role' => DisputeEvent::ROLE_ADMIN,
            'body' => "Dispute assignée à {$admin->name}",
            'visibility' => DisputeEvent::VISIBILITY_PRIVATE,
            'payload' => ['assigned_to' => $admin->id],
        ]);

        ActivityLogger::log('dispute.assigned', $case, [
            'admin_user_id' => $admin->id,
        ]);

        return $case->fresh();
    }

    public function escalate(ComplaintCase $case, ?string $reason = null): ComplaintCase
    {
        $newLevel = $case->escalation_level + 1;

        $case->update([
            'escalation_level' => $newLevel,
            'escalated_at' => now(),
            'status' => ComplaintCase::STATUS_ESCALATED,
            'priority' => ComplaintCase::PRIORITY_HIGH,
            'last_activity_at' => now(),
            'due_at' => $this->slaService->computeDueAt(
                ComplaintCase::PRIORITY_URGENT,
                $case->severity ?? ComplaintCase::SEVERITY_HIGH,
            ),
        ]);

        $this->recordEvent($case, DisputeEvent::TYPE_ESCALATED, [
            'author_role' => DisputeEvent::ROLE_SYSTEM,
            'body' => $reason ?? "Escaladé automatiquement (SLA dépassé)",
            'visibility' => DisputeEvent::VISIBILITY_PRIVATE,
            'payload' => ['new_level' => $newLevel, 'reason' => $reason],
        ]);

        ActivityLogger::log('dispute.escalated', $case, [
            'new_level' => $newLevel,
            'reason' => $reason,
        ]);

        DisputeEscalated::dispatch($case, $newLevel);

        return $case->fresh();
    }

    public function recordEvent(ComplaintCase $case, string $type, array $attributes = []): DisputeEvent
    {
        return DisputeEvent::create(array_merge([
            'complaint_case_id' => $case->id,
            'type' => $type,
            'visibility' => DisputeEvent::VISIBILITY_ALL,
            'author_role' => DisputeEvent::ROLE_SYSTEM,
        ], $attributes));
    }

    protected function roleOf(User $actor, ComplaintCase $case): string
    {
        if ($actor->isPlatformAdmin() || $actor->isAdmin()) {
            return DisputeEvent::ROLE_ADMIN;
        }
        if ((int) $actor->id === (int) $case->provider_user_id) {
            return DisputeEvent::ROLE_PROVIDER;
        }
        if ((int) $actor->id === (int) $case->client_id) {
            return DisputeEvent::ROLE_CLIENT;
        }
        return DisputeEvent::ROLE_SYSTEM;
    }

    protected function notifyOpened(ComplaintCase $case): void
    {
        try {
            if ($case->client) {
                $case->client->notify(new DisputeOpenedNotification($case));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function notifyUpdate(ComplaintCase $case, ?User $author): void
    {
        try {
            foreach ([$case->client, $case->provider] as $recipient) {
                if (! $recipient) continue;
                if ($author && (int) $recipient->id === (int) $author->id) {
                    continue;
                }
                $recipient->notify(new DisputeUpdatedNotification($case));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function generateReference(): string
    {
        $prefix = (string) config('disputes.reference_prefix', 'DSP');
        do {
            $candidate = $prefix . '-' . strtoupper(Str::random(8));
        } while (ComplaintCase::where('reference', $candidate)->exists());

        return $candidate;
    }
}
