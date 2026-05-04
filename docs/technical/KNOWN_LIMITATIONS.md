# Known limitations — CleanUx

## API tokens

Certains tests Jetstream peuvent être skipped si le support API n'est pas activé.

Ce n'est pas bloquant si CleanUx n'utilise pas les API tokens en production.

## Warning Collision / PHP

Un warning peut apparaître :

Deprecated: Method ReflectionMethod::setAccessible() is deprecated

Ce warning ne bloque pas la suite tant que les tests passent.

## Services externes

Certains services doivent être mockés en test :

- OpenStreetMap / Nominatim ;
- Google Calendar ;
- Stripe ;
- Mailer réel.

En test, il faut utiliser des fakes Laravel quand c'est possible.

## Tests plus longs

Certains tests peuvent être plus longs car ils rendent beaucoup de vues Livewire :

- AdminRouteAccessTest
- ClientRouteAccessTest
- EmployeRouteAccessTest
- OptimizedDashboardExperienceSmokeTest
