<?php

namespace Tests\Feature\Automation;

/** Les cles reellement emises par BusinessAlerts, lues a la source — jamais recopiees a la main. */
trait ExtraitLesClesEmises
{
    /** @return list<string> */
    private function clesEmises(): array
    {
        $source = (string) file_get_contents(app_path('Support/Alerts/BusinessAlerts.php'));

        preg_match_all("/key:\s*'([a-z_]+)'/", $source, $trouvees);

        return array_values(array_unique($trouvees[1]));
    }
}
