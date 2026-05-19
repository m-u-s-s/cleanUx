<?php

namespace App\Events\Gdpr;

use App\Models\GdprDataRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GdprExportReady
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public GdprDataRequest $request)
    {
    }
}
