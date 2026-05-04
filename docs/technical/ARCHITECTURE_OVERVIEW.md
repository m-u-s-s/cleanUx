# CleanUx — Technical Architecture Overview

## Stack principale

- Laravel
- Livewire
- Jetstream
- Sanctum
- Blade
- Policies
- Notifications
- Commands
- Feature tests

## Domaines principaux

### Booking

Le booking couvre :

- client
- service catalog
- zone
- postal code
- employee assignment
- snapshots structurés
- recurring booking
- enterprise site context

### Finance

La finance couvre :

- quotes
- invoices
- documents client
- paiement partiel
- règles zone
- discount enterprise
- billing profile pays

### Enterprise / B2B

Le B2B couvre :

- organization accounts
- organization sites
- contracts
- purchase order requirements
- work orders
- approvals
- monthly invoice generation

### Missions employé

Les missions couvrent :

- assignment
- workspace employé
- statut mission
- suivi terrain
- incidents
- coordination
- équipes et team lead

### International / Zones

Ce domaine couvre :

- countries
- regions
- provinces
- postal codes
- service zones
- zone rules
- country billing profile
- international operational rules

### Gouvernance

La gouvernance couvre :

- audit logs
- security hardening
- readiness reports
- production health checks
- module activation
- go-live checklist

## Règle Livewire importante

Chaque composant Livewire doit avoir un seul élément racine.

Correct :

    <div>
        ...
    </div>

Incorrect :

    <div>...</div>
    <section>...</section>
