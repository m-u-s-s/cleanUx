# Bloc 14 — Règles pays appliquées au booking / finance / documents

Ce bloc branche réellement les réglages pays dans le produit :

- **booking** : activation marché, service activé, délai minimum pays, prix multipliés par marché
- **finance** : devise, taux taxe, délais de paiement, préfixes devis/factures
- **documents** : format date, symbole devise, libellé taxe, rendu PDF et portail client

## Fichiers clés
- `app/Services/International/CountryMarketResolver.php`
- `app/Services/Booking/CreateBookingAction.php`
- `app/Services/Booking/BookingEstimatorService.php`
- `app/Services/Finance/FinanceDocumentService.php`
- `app/Models/Concerns/InteractsWithDocumentFormatting.php`

## Vérifications conseillées
```bash
composer dump-autoload
php artisan optimize:clear
php artisan test tests/Unit/CountryMarketResolverTest.php
php artisan test tests/Unit/BookingEstimatorServiceInternationalRulesTest.php
php artisan test tests/Unit/FinanceDocumentServiceInternationalRulesTest.php
php artisan test
```
