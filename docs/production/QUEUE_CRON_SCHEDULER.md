# Brio — Files, ordonnanceur et sonde de vie

Trois choses doivent tourner en production, et chacune est déjà tombée en panne en silence :

1. **l'ordonnanceur** (`schedule:run`, chaque minute) — sinon aucune des tâches planifiées n'existe ;
2. **les workers de files** — sur la **même connexion** que l'application, sinon ils vident une
   banque de jobs vide pendant que l'application en remplit une autre ;
3. **la sonde** qui constate que 1 et 2 tournent encore, sans être elle-même planifiée par 1.

Les exemples prêts à copier sont dans `deploy/systemd/` et `deploy/supervisor/`.
`tests/Feature/Ops/DeploiementFilesEtOrdonnanceurTest.php` relit ces fichiers et échoue si l'un
d'eux se désaligne de `.env.production.example` ou de ce que `app/` fait réellement.

---

## 1. Ordonnanceur

Une seule des deux méthodes, jamais les deux (sinon chaque tâche part en double).

**Crontab** (au nom de l'utilisateur web) :

```
* * * * * cd /var/www/brio && php artisan schedule:run >> /dev/null 2>&1
```

**Ou systemd** — `deploy/systemd/brio-scheduler.service.example` + `brio-scheduler.timer.example` :

```bash
sudo cp deploy/systemd/brio-scheduler.service.example /etc/systemd/system/brio-scheduler.service
sudo cp deploy/systemd/brio-scheduler.timer.example   /etc/systemd/system/brio-scheduler.timer
sudo systemctl daemon-reload
sudo systemctl enable --now brio-scheduler.timer
systemctl list-timers 'brio-*'      # NEXT / LAST doivent bouger chaque minute
```

Le timer porte `AccuracySec=1s` : sans lui, systemd regroupe les déclenchements dans une fenêtre
d'une minute et fait sauter les tâches « chaque minute » (`presence:cleanup`, `surge:recompute`).

---

## 2. Sonde : l'ordonnanceur est-il vivant ?

Le dépôt possède déjà un battement de cœur ; il ne faut surtout pas en écrire un second.

| Pièce | Rôle |
|---|---|
| `app:ops-heartbeat` (planifié, toutes les 5 min) | écrit `storage/app/ops/heartbeat.json` + la clé de cache `brio:ops:heartbeat` |
| `App\Services\Ops\ProductionHealthReport` | relit ce battement et passe le check « Heartbeat récent » en ERREUR au-delà de `OPS_HEARTBEAT_MAX_AGE_SECONDS` |
| `app:production-health-check --strict` | rend un code de sortie 1 dès qu'un check ERREUR échoue |

Comme le battement est écrit **par** l'ordonnanceur, un ordonnanceur arrêté fait vieillir le
fichier et la sonde vire au rouge en `OPS_HEARTBEAT_MAX_AGE_SECONDS` (900 s par défaut, soit trois
battements manqués).

D'où la règle : **la sonde a son propre timer**, elle n'est pas planifiée par ce qu'elle surveille.

```bash
sudo cp deploy/systemd/brio-scheduler-heartbeat.service.example /etc/systemd/system/brio-scheduler-heartbeat.service
sudo cp deploy/systemd/brio-scheduler-heartbeat.timer.example   /etc/systemd/system/brio-scheduler-heartbeat.timer
sudo systemctl daemon-reload
sudo systemctl enable --now brio-scheduler-heartbeat.timer
systemctl status brio-scheduler-heartbeat.service    # « failed » = l'ordonnanceur ne bat plus
```

Décommenter `OnFailure=` dans le service pour brancher l'alerte (mail, Slack, PagerDuty).
Sur un hébergement sans systemd, la même sonde s'appelle depuis un cron *séparé* ou depuis une
supervision externe : `php artisan app:production-health-check --strict`.

---

## 3. Workers de files

### La connexion : le piège qui a coûté le plus cher

Le premier argument positionnel de `queue:work` est la **CONNEXION**, pas la file. Les exemples
lançaient la connexion `database` alors que `.env.production.example` déclare
`QUEUE_CONNECTION=redis` : les workers drainaient la table `jobs` pendant que l'application
poussait dans Redis. Aucun job traité, aucune erreur — et donc, entre autres, plus aucune escalade
du moteur de répartition (`EscalateMissionAssignmentJob` part sur la file `default` avec un
`->delay()` : sans worker, le TTL de 20 s et les vagues ne se déclenchent jamais).

**Règle : ne jamais nommer la connexion.** Sans argument positionnel, artisan lit
`config('queue.default')`, c'est-à-dire `QUEUE_CONNECTION` : le worker suit la production toute
seule, sans valeur à resynchroniser à la main.

### Les files à drainer

`--queue` est en revanche obligatoire : sans lui, un worker ne draine **que** `default` et
abandonne tous les webhooks.

| File | Qui y pousse |
|---|---|
| `default` | tout ce qui est dispatché sans `onQueue()`, dont l'escalade du dispatch |
| `stripe-webhooks` | `StripeConnectWebhookController`, `StripeHardeningCenter` |
| `kyc-webhooks` | `KycWebhookController` |
| `sms-webhooks` | `SmsWebhookController` |
| `insurance-webhooks` | `InsuranceWebhookController` |
| `webhooks` | `WebhookDispatcher`, `WebhooksV2Controller`, `WebhooksCenter` (`config('webhooks_v2.queue')`) |
| `antivirus` | `AttachmentUploadService` — scans longs, worker dédié |
| `payments`, `stripe`, `notifications`, `analytics`, `reports` | groupes de priorité déclarés par `config/queue.php` ; aucun `onQueue()` de `app/` ne les vise aujourd'hui, mais les drainer ne coûte rien et évite le trou noir silencieux du jour où l'un servira |

### Supervisor

`deploy/supervisor/brio-worker.conf.example` (extrait) :

```ini
[program:brio-worker]
command=/usr/bin/php8.5 /var/www/brio/artisan queue:work --queue=payments,stripe,stripe-webhooks,kyc-webhooks,sms-webhooks,insurance-webhooks,webhooks,default,notifications,analytics,reports --sleep=3 --tries=3 --timeout=120 --max-time=3600 --backoff=10,30,90
numprocs=4

[program:brio-worker-av]
command=/usr/bin/php8.5 /var/www/brio/artisan queue:work --queue=antivirus --sleep=5 --tries=2 --timeout=600 --max-time=3600
numprocs=1
```

`antivirus` est absent du worker généraliste **exprès** : un scan dépasse `--timeout=120` et se
ferait tuer puis rejouer en boucle.

### systemd

```bash
sudo cp deploy/systemd/brio-queue.service.example           /etc/systemd/system/brio-queue.service
sudo cp deploy/systemd/brio-queue-antivirus.service.example /etc/systemd/system/brio-queue-antivirus.service
sudo systemctl daemon-reload
sudo systemctl enable --now brio-queue.service brio-queue-antivirus.service
```

Après chaque déploiement : `php artisan queue:restart` (les workers rechargent le code à la fin du
job courant).

---

## 4. Inventaire des tâches planifiées (`app/Console/Kernel.php`)

| Commande | Fréquence | Ce qui casse sans elle |
|---|---|---|
| `presence:cleanup` | Chaque minute | Lignes de présence fantômes |
| `surge:recompute` | Chaque minute | Multiplicateurs de tarification figés |
| `presence:scan-stale --threshold=5` | Toutes les 2 min | « En ligne » faux, dont dépend `CandidateFinder` |
| `face-check:maintenance` | Toutes les 5 min | Contrôle facial ouvert et jamais répondu : il bloque la réouverture du suivant, et le prestataire reste devant un écran mort |
| `extras:reprendre-les-prelevements` | Toutes les heures | Supplément accepté par le client et jamais encaissé : le prestataire a fait le travail, la créance existe, et rien ne la rejouait |
| `app:ops-heartbeat` | Toutes les 5 min | La sonde ci-dessus n'a plus rien à lire |
| `app:send-rendezvous-reminders` | Toutes les 15 min | Rappels client/prestataire |
| `google-calendar:sync --future-days=30` | Toutes les 15 min | Agendas Google désynchronisés |
| `app:send-smart-rdv-notifications` | Toutes les 15 min | Notifications contextuelles |
| `contract:scan-sla` | Toutes les 15 min | SLA contractuels jamais marqués ni escaladés |
| `ops:check-providers --strict` | Toutes les 30 min | Conformité prestataires non contrôlée |
| `finance:sync-documents` | Horaire | Documents financiers non synchronisés |
| `masked-calls:scan-expired` | Horaire | Numéros proxy loués jamais rendus (coût mensuel + rappel possible après l'intervention) |
| `bundles:scan-quote-requests` | Horaire | Demandes de devis multi-métiers en souffrance |
| `stripe:retry-failed-webhooks` | Horaire | Webhooks Stripe en échec transitoire jamais rejoués |
| `app:production-health-check` | Horaire | Rapport de santé (log) |
| `backup:clean` | Quotidien 01:00 | Sauvegardes qui s'empilent |
| `backup:run` | Quotidien 01:30 | **Aucune sauvegarde** |
| `payouts:process` | Quotidien 02:00 | Commissions et virements prestataires |
| `app:prune-read-notifications --days=30` | Quotidien 02:30 | Table de notifications qui gonfle |
| `subscriptions:tick --limit=500` | Quotidien 03:00 | Cycles de facturation d'abonnement |
| `gdpr:enforce-retention` | Quotidien 04:00 | Rétention légale non appliquée |
| `gdpr:execute-erasures` | Quotidien 04:30 | Droit à l'oubli non exécuté |
| `fleet:scan-expiring` | Quotidien 05:00 | Certificats/assurances véhicules expirés non signalés |
| `provider:scan-expiring-documents` | Quotidien 05:15 | Permis/carte grise/assurance qui périment **sans prévenir** : le dispatch écarte le prestataire du jour au lendemain, dossier « tout vert » à l'écran |
| `stripe:reconcile --scope=all --days=1` | Quotidien 05:30 | Écarts Stripe ↔ base non détectés |
| `currencies:refresh` | Quotidien 06:00 | Taux de change figés (tous les prix convertis) |
| `finance:sync-documents --reminders` | Quotidien 09:00 | Relances de factures impayées |
| `bookings:process-recurring` | Quotidien 06:30 | Réservations récurrentes jamais créées |
| `backup:monitor` | Quotidien 07:00 | Santé des sauvegardes |
| `app:generate-subscriptions` | Quotidien | Factures d'abonnement non générées |
| `nps:send-surveys` | Quotidien 10:00 | Enquêtes NPS |
| `accounting:close-previous-month` | Mensuel (6) 04:00 | Période comptable non clôturée |
| `backup:verify` | Mensuel | Intégrité des sauvegardes jamais vérifiée |
| `PurgeAuditEventsJob` (job) | Quotidien 03:15 | Purge des événements d'audit |
| `marketing:recompute-segments` (job) | Quotidien 02:00 | Segments marketing périmés |
| `marketing:dispatch-steps` (job) | Toutes les 10 min | Étapes de campagne drip |
| `RefreshFxRatesJob` (job) | Quotidien 06:15 | Rafraîchissement FX asynchrone |

Les tâches marquées « (job) » sont mises en file : elles ont besoin de l'ordonnanceur **et** d'un
worker. C'est la double dépendance qui rend le désalignement de connexion si coûteux.

---

## 5. Configuration recommandée en production

```env
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Sonde : âge maximal toléré du battement de cœur avant alerte (secondes).
OPS_HEARTBEAT_MAX_AGE_SECONDS=900
```

Les workers n'ont pas à connaître cette valeur : ils la lisent via `config('queue.default')`.

---

## 6. Vérifications après déploiement

```bash
systemctl list-timers 'brio-*'                       # l'ordonnanceur bat chaque minute
php artisan app:production-health-check              # « Heartbeat récent » doit être vert
php artisan queue:monitor default,stripe-webhooks    # backlog par file
php artisan queue:failed                             # jobs morts
```
