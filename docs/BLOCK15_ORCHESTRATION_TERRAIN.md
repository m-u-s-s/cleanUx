# Bloc 15 — Orchestration terrain multi-équipe / mission batch / chantier multi-jours

## Objectif
Passer d'une mission isolée à une orchestration terrain pilotable par lot, jour et segment d'exécution.

## Nouvelles briques
- MissionBatch
- MissionBatchDay
- MissionTaskSegment
- MissionBatchPlannerService
- Centre admin d'orchestration terrain
- Vue chef d'équipe / coordination chantier

## Cas couverts
- chantier multi-jours
- bureaux multi-équipes
- segmentation par zone de travail
- rattachement d'une mission existante à un segment terrain

## Étapes suivantes
- rattacher les MissionTeamAssignment et MissionPartnerAssignment à chaque segment
- calculer la charge réelle par équipe et par partenaire
- générer des missions depuis les lots et work orders
- ajouter les validations fin de journée / fin de lot
