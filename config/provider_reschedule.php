<?php

/*
|--------------------------------------------------------------------------
| Reprogrammation par le PRESTATAIRE
|--------------------------------------------------------------------------
|
| Une société doit pouvoir décaler une intervention — un embouteillage, une clé non remise, un
| chantier qui déborde. Jusqu'ici elle ne le pouvait pas : le service de reprogrammation était
| strictement client/admin, et la seule issue était d'appeler le client pour qu'il le fasse lui-même.
|
| APPLICATION IMMÉDIATE, PAS D'ACCORD PRÉALABLE. Un accord transformerait chaque aléa de tournée en
| négociation, et vingt minutes de retard ne peuvent pas attendre une réponse. Le client est prévenu
| systématiquement, et c'est ce qui rend l'immédiateté acceptable.
*/

return [

    /*
     * LA FENÊTRE DE GEL. Déplacer une intervention la veille au soir n'est pas la même décision que
     * la déplacer la semaine précédente : le client a organisé sa journée autour, et il est
     * peut-être trop tard pour qu'il s'adapte.
     *
     * La borne n'interdit pas — elle relève le niveau de décision, et exige un motif que le client
     * lira dans sa notification.
     */
    'freeze_window_hours' => 24,

    /*
     * Qui décide sous la fenêtre de gel. Volontairement COURT : c'est une décision qui engage la
     * relation client, pas un ajustement de planning.
     */
    'freeze_window_roles' => ['owner', 'operations_manager'],
];
