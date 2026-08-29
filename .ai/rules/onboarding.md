---
paths:
  - 'app/Actions/Tenancy/**,app/Http/Controllers/OnboardingController.php,app/Http/Requests/Onboarding/**'
---

# Onboarding

## Provision first owner workspace atomically
Initial owner onboarding must create tenant, active owner membership, tenant roles, first outlet, and its tax setting in one database transaction. Keep Fortify's general home at dashboard; bind RegisterResponse separately so only new registrations go to onboarding.
