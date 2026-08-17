/**
 * L'HORLOGE D'UNE MISSION VENDUE AU TEMPS, telle que le serveur la décrit.
 *
 * CE TYPE EST UN CONTRAT, pas une commodité. Chaque champ ci-dessous est produit par
 * `App\Services\Missions\HourlyMissionClock::etat()` et par lui seul. En ajouter un ici sans
 * l'ajouter là-bas donne un écran qui compile, qui passe ses tests contre un objet fabriqué à la
 * main, et qui affiche `undefined` à un client — le piège vérifié de ce dépôt.
 */
export interface MissionClock {
  /**
   * FAUX signifie « cette mission ne se vend pas au temps », et l'horloge ne doit alors rien
   * afficher du tout. Ce n'est pas un état de chargement : c'est une réponse.
   */
  applies: boolean;

  /** L'heure du SERVEUR à l'instant de la réponse. Sert à corriger l'horloge de l'appareil. */
  server_now?: string;

  /** Le démarrage réel constaté sur le terrain, pas l'heure du rendez-vous. */
  started_at?: string;

  /** Ce que le client a acheté. */
  purchased_minutes?: number;

  /** L'instant où le temps acheté s'épuise. */
  deadline_at?: string;

  /**
   * L'instant à partir duquel le dépassement devient FACTURABLE — soit l'échéance plus la
   * franchise. Distinct de `deadline_at` à dessein : les confondre ferait croire que la pénalité
   * tombe à la seconde où le temps est écoulé.
   */
  billable_from_at?: string;

  /** Les minutes de dépassement offertes avant toute facturation. */
  grace_minutes?: number;

  elapsed_minutes?: number;

  /** NÉGATIF au-delà de l'échéance : c'est ce qui distingue « il reste » de « on déborde ». */
  remaining_minutes?: number;

  overrun_minutes?: number;

  /** Le dépassement réellement facturable : franchise déduite, arrondi, plafonné. */
  billable_overtime_minutes?: number;

  cap_minutes?: number;

  /** Le plafond est atteint : le compteur ne coûte plus rien de plus. */
  capped?: boolean;

  overtime_multiplier?: number;

  /** Le tarif horaire RÉELLEMENT payé sur cette réservation, majorations comprises. */
  effective_hourly_rate_cents?: number;

  /**
   * CE QUE LE DÉPASSEMENT COÛTE — calculé par le serveur, JAMAIS par l'application.
   *
   * L'application fait défiler des secondes ; elle ne fabrique pas d'euros. Le jour où un écran
   * multiplierait lui-même un tarif par une durée, il existerait deux montants pour la même
   * mission, et c'est celui de l'appareil que le client aurait lu.
   */
  overtime_amount_cents?: number;

  /**
   * LA RÈGLE, ÉCRITE PAR LE SERVEUR — jamais par l'application.
   *
   * Même principe que le texte de consentement du contrôle facial : une seule source
   * (`lang/<code>/pricing.php`) et les deux surfaces affichent la même phrase, dans la langue du
   * compte. Une rédaction locale divergerait des conditions générales dès la première modification,
   * et c'est la copie non relue qui serait lue au moment où ça coûte.
   */
  rule?: {
    /** Pour le client : ce qu'il peut décider. */
    short?: string;
    /** Pour le prestataire : ce qu'il déclenche, et ce qu'il touche. */
    provider?: string;
  };
}

/**
 * Où en est la mission par rapport au temps acheté.
 *
 *   `idle`      — pas démarrée, ou pas vendue au temps : rien à afficher.
 *   `running`   — dans le temps acheté.
 *   `ending`    — dans les quinze dernières minutes ; c'est le moment de proposer une prolongation.
 *   `grace`     — le temps est écoulé, la franchise court, rien n'est encore facturé.
 *   `overtime`  — le dépassement est facturé.
 */
export type MissionClockPhase = 'idle' | 'running' | 'ending' | 'grace' | 'overtime';

/** L'horloge du serveur, augmentée du décompte qui défile sur l'appareil. */
export interface LiveMissionClock {
  applies: boolean;
  phase: MissionClockPhase;

  /** Secondes écoulées depuis le démarrage réel, corrigées du décalage de l'appareil. */
  elapsedSeconds: number;

  /** Secondes restantes avant l'échéance. NÉGATIF au-delà. */
  remainingSeconds: number;

  /** Secondes restantes de franchise, une fois l'échéance passée. Zéro ailleurs. */
  graceSeconds: number;

  /** Fraction du temps acheté consommée, bornée à 1 pour la barre de progression. */
  progress: number;

  /** Le contrat serveur, intact — c'est de là que viennent tous les montants. */
  server: MissionClock;
}
