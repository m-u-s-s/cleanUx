<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\MissionVerificationCode;
use App\Models\User;
use App\Services\Missions\MissionLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeMissionQrController extends Controller
{
    public function validateQr(
        Request $request,
        Mission $mission,
        string $type,
        int $codeId,
        MissionLifecycleService $lifecycle
    ) {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->isEmploye(), 403);

        abort_unless(in_array($type, ['start', 'end'], true), 404);

        $record = MissionVerificationCode::query()
            ->where('id', $codeId)
            ->where('mission_id', $mission->id)
            ->where('code_type', $type)
            ->where('is_consumed', false)
            ->firstOrFail();

        abort_unless(
            $mission->lead_employee_id === $user->id
            || $mission->assignments()->where('user_id', $user->id)->exists(),
            403
        );

        $record->update([
            'validated_by_user_id' => $user->id,
            'validated_at' => now(),
            'is_consumed' => true,
        ]);

        if ($type === 'start') {
            $lifecycle->validateStartCodeFromQr($mission, $user);

            return redirect()
                ->route('employe.missions')
                ->with('success', 'Mission démarrée via QR code.');
        }

        $lifecycle->completeMission($mission, $user);

        return redirect()
            ->route('employe.missions')
            ->with('success', 'Mission terminée via QR code.');
    }
}