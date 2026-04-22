# Réservation zone-aware

## 1. But

Le moteur de réservation doit garantir qu'un rendez-vous est :
- compatible avec la zone couverte,
- compatible avec le service demandé,
- compatible avec la capacité et les disponibilités,
- traçable même après évolution du catalogue ou des règles.

## 2. Références structurées

Le rendez-vous s'appuie d'abord sur :
- `service_catalog_id`
- `service_zone_id`
- `postal_code_id`

Les anciens champs texte peuvent encore servir de snapshot/fallback, mais ils ne doivent plus être la source de vérité principale.

## 3. Entrée utilisateur

Le tunnel Livewire utilise désormais une logique claire :
- identifiant de service sélectionné,
- code postal saisi,
- résolution de zone,
- résolution du service,
- estimation,
- validation,
- création.

## 4. Résolution de couverture

La couverture s'appuie sur :
1. code postal / ville,
2. site entreprise si présent,
3. zone directement reliée,
4. fallback province,
5. fallback région,
6. fallback national.

Le moteur doit aussi gérer :
- zone inactive,
- zone visible non réservable,
- validation manuelle,
- service non autorisé,
- capacité atteinte,
- employé hors zone.

## 5. Affectation employé

L'affectation tient compte de :
- couverture de zone,
- priorité d'affectation,
- disponibilité,
- capacité,
- premium / sélection employé,
- buffer entre missions.

## 6. Snapshots

À la création, le rendez-vous garde :
- `zone_snapshot`
- `pricing_snapshot`
- des informations d'affichage service / localisation exploitables côté finance et exports

Cela sécurise :
- les devis,
- les factures,
- les exports,
- l'historique.

## 7. Cas enterprise

Pour un compte entreprise, la réservation peut dépendre :
- du site sélectionné,
- des règles contractuelles,
- d'une référence PO,
- de validations manuelles,
- d'un périmètre autorisé par organisation.

## 8. Cas récurrents

Le moteur doit permettre :
- création de série,
- occurrences structurées,
- modification d'une seule occurrence,
- modification de toute la série,
- pause / reprise / annulation future.
