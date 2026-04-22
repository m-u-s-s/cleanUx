# Bloc 12 — B2B lourd plus profond

Ce bloc étend les fondations du bloc 11 avec un vrai domaine entreprise avancé :

- `OrganizationContract` pour le cadre contractuel explicite
- `EnterpriseWorkOrder` pour les demandes lourdes / multisites / chantiers
- `WorkOrderLine` pour le découpage commercial/opérationnel
- `WorkOrderApproval` pour les validations B2B
- `MissionBatch` pour préparer le découpage d'un work order en plusieurs missions

## Écrans ajoutés
- `admin.b2b.operations` : centre de pilotage B2B lourd

## Idée directrice
On passe de :
- compte entreprise + site + metadata

à :
- contrat explicite
- ordre de service structuré
- approbation
- budget / PO / cost center
- équipe / partenaire dédiés
- futur découpage en lots de missions
