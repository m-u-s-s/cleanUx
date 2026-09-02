# Dispatch Domain

Matches bookings with available providers.

- **MatchingScoreEngine** — le scoreur employé par `CandidateFinder`, poids dans `config/matching.php`
- **MatchingScoreEngine** — full 9-criteria V2 engine with audit trail
- **SmartDispatchService** — auto-assign + timeout + fallback chain
