# CleanUx Mobile RN — Sprint 4-5 : Booking Flow + Browse Providers

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Parcours booking client 5 étapes fonctionnel end-to-end (service → details → coordinates → scheduling → confirmation), BrowseProviders avec filtres, liste de bookings client, détail booking. Consomme les API existantes.

**Architecture:** Booking wizard = nested stack navigator avec un `useReducer` BookingState partagé via context. Chaque step est un écran indépendant qui lit/écrit dans le state. API hooks TanStack Query. Composants UI du Sprint 3.

**API endpoints (existants, vérifiés):**
- `GET /api/search/services` — liste services/trades publique
- `GET /api/v2/pricing/services` — service catalog + pricing
- `GET /api/search/providers` — recherche providers avec filtres
- `GET /api/search/postal-autocomplete` — autocomplétion code postal
- `GET /api/geo/v2/autocomplete` — autocomplétion adresse (auth)
- `POST /api/client/bookings` — créer une réservation
- `GET /api/client/bookings` — liste des réservations client
- `GET /api/client/bookings/{id}` — détail réservation

---

## 3 Batches

### Batch 1: Foundation (API hooks + booking state + types)
### Batch 2: Booking wizard 5 screens + confirmation
### Batch 3: BrowseProviders + BookingsList + BookingDetail + navigation wiring
