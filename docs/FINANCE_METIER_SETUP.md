# Bloc finance métier

Ce patch ajoute une couche métier finance conservative autour de CleanUx :

- devis structurés (`finance_quotes`)
- factures structurées (`finance_invoices`)
- paiements enregistrés (`finance_payments`)
- relances (`finance_reminders`)
- synchronisation automatique depuis les rendez-vous

## Commande disponible

```bash
php artisan finance:sync-documents
php artisan finance:sync-documents --reminders
```

## Scheduler ajouté

- synchronisation des devis/factures : toutes les heures
- relances : tous les jours à 09:00

## Notes

- rien n'est supprimé
- le centre finance existant est conservé et enrichi
- les documents sont liés au rendez-vous et à l'organisation si présente
- le statut des factures se recalculera après chaque paiement
