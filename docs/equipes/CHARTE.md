# Charte des 4 équipes — plateforme CleanUx

Dispositif permanent. Quatre équipes, quatre sessions, un seul dépôt.
Posé le 2026-08-19. Ne se redemande pas.

---

## T4 — COMITÉ DE PILOTAGE (l'équipe qui commande)

**C'est la seule équipe à qui l'utilisateur parle.** Elle reçoit les indications,
les traduit en directives, les distribue, et prononce le verdict final.

| Siège | Ce qu'il tient |
|---|---|
| Président (boucle principale) | Reçoit l'utilisateur. Découpe la mission en lots. Tranche les désaccords entre T1/T2/T3. Prononce ✅ / ❌. |
| Architecte en chef | Frontières entre les trois périmètres. Refuse toute conception créant deux sources de vérité entre client / provider / admin. |
| Auditeur sécurité en chef | Cherche le contournement inter-rôles : un client qui atteint une donnée provider, un provider qui atteint l'admin. |
| Qualité en chef | Exige le témoin positif de chaque test de refus. Traque le vert obtenu pour une mauvaise raison. |
| Designer en chef | Impose la direction visuelle et refuse ce qui la trahit, sur les trois périmètres à la fois. |

**T4 ne code pas.** Elle commande, relit, refuse, et ne clôt rien tant qu'un lot
n'est pas à 100 %.

---

## T1 — CLIENT (web & natif)

**Rôles tenus** : `client_individuelle`, `client_societe`
**Sous-rôles d'organisation côté client** : `owner`, `finance`, `viewer`,
`manager`, `site_manager`, `requester`

**Surface réelle mesurée le 2026-08-19**
- `app/Livewire/Client/` — 43 composants
- `app/Livewire/ClientCompany/` — 13 composants
- `app/Livewire/OrderEngine/` — 4 composants (le moteur de commande est un chemin client)
- `routes/client.php` (285 l.), part client de `routes/company-dashboards.php` (156 l.)
- `mobile/client/` — 52 écrans natifs
- Domaines : réservation, moteur de commande, paiement, fidélité, avis, suivi,
  litiges côté demandeur, favoris, parrainage.

---

## T2 — PROVIDER (web & natif)

**Rôles tenus** : `provider_individuelle`, `provider_societe`
**Sous-rôles d'organisation côté provider** : `operations_manager`, `dispatcher`,
`team_lead`, `worker`, `quality_manager` (+ `owner`, `finance`, `viewer` en version provider)

**Surface réelle mesurée le 2026-08-19**
- `app/Livewire/Provider/` — 17 composants
- `app/Livewire/ProviderCompany/` — 15 composants
- `app/Livewire/Employe/` — 19 composants (le salarié est un rôle provider)
- `routes/employe.php` (207 l.), `routes/missions.php` (94 l.), part provider de
  `routes/company-dashboards.php`
- `mobile/provider/` — 71 écrans natifs
- Domaines : missions, répartition/ASAP, disponibilités, présence, inspection,
  gains et règlement, flotte, badges, contrôle facial, checklists de clôture.

---

## T3 — ADMIN (web & natif)

**Rôles tenus** : `admin`, `super_admin`

**Surface réelle mesurée le 2026-08-19**
- `app/Livewire/Admin/` — 105 composants
- `app/Livewire/SuperAdmin/` — 2 composants
- `routes/admin.php` (703 l. — le plus gros fichier de routes du dépôt)
- `mobile/provider/src/admin/` — console admin native
- Domaines : catalogue géographique, tarification, comptabilité, KYC/KYB, litiges,
  fraude, journal d'audit, drapeaux de fonctionnalité, supervision.

---

## Frontières partagées — arbitrage T4 obligatoire

Ces zones appartiennent à deux équipes à la fois. **Aucune ne les modifie seule.**

| Zone | Équipes | Pourquoi c'est piégé |
|---|---|---|
| `app/Enums/OrganizationRole.php` | T1 + T2 | `owner`/`finance`/`viewer` existent des deux côtés ; le sens dépend du type d'organisation. |
| `routes/company-dashboards.php` | T1 + T2 | Un seul fichier, deux publics. |
| Réservation / mission (même objet, deux vues) | T1 + T2 | `booking_id` fait foi. Deux notions un événement = LE défaut dominant du dépôt. |
| Annulation, remboursement, frais | T1 + T2 + T3 | L'argent traverse les trois périmètres. |
| Chat, notifications, présence | T1 + T2 | Un émetteur, un destinataire, deux écrans. |
| Drapeaux de fonctionnalité, catalogue, tarifs | T3 → T1 + T2 | L'admin ouvre ou ferme ce que les deux autres consomment. |
| `mobile/shared/` | T1 + T2 (+ T3 pour la console) | Un seul code partagé par les deux applications natives. |

---

## Non négociables communs aux quatre équipes

1. **Analyser le code réel.** Jamais la documentation, jamais la mémoire d'une
   session passée. On cite `fichier:ligne`, puis on corrige.
2. **Joignabilité avant existence.** Sur ce dépôt, la famille de défauts dominante
   est le module complet et injoignable : `grep` les appelants AVANT d'écrire.
3. **Un test de refus exige un témoin positif.** Sinon le vert mesure une panne.
4. **Ne jamais éditer un fichier pendant qu'une suite tourne.**
5. **Personne ne valide son propre travail.** Le refus se motive par une preuve,
   pas par une opinion.
