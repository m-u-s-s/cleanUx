<?php

namespace App\Models;

use Database\Factories\JobApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UNE CANDIDATURE (E25).
 *
 * LE CANDIDAT N'EST PAS UN UTILISATEUR, et c'est la décision structurante de ce module. Exiger un
 * compte avant de postuler diviserait les candidatures par cinq : le nom, le courriel et le
 * téléphone vivent donc ici, et `user_id` n'est rempli que si la personne se trouve déjà être
 * quelqu'un de la plateforme.
 *
 * @property int $id
 * @property int $job_posting_id
 * @property int|null $user_id
 * @property string $full_name
 * @property string $email
 * @property string $status
 * @property int|null $organization_invitation_id
 */
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use HasFactory;

    public const STATUS_RECEIVED = 'received';

    /** Retenue pour la suite — sans engagement : c'est l'embauche qui engage. */
    public const STATUS_SHORTLISTED = 'shortlisted';

    /** Embauché : une invitation à jeton a été émise, et elle seule crée le collègue. */
    public const STATUS_HIRED = 'hired';

    /** Conservée : un refus effacé, c'est la même candidature qui revient dans six mois. */
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'job_posting_id',
        'user_id',
        'full_name',
        'email',
        'phone',
        'message',
        'cv_path',
        'status',
        'decided_by_user_id',
        'decided_at',
        'decision_note',
        'organization_invitation_id',
        'metadata',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<JobPosting, $this> */
    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    /** @return BelongsTo<OrganizationInvitation, $this> */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(OrganizationInvitation::class, 'organization_invitation_id');
    }
}
