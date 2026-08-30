---
paths:
  - app/Models/StaffNotificationPreference.php
---

# App Models

## Scope staff notification preferences by outlet
Staff notification preferences must remain unique per tenant, outlet, and user. Rely on the tenant/outlet global scopes plus the database unique index; do not look up or update them across the active outlet context.
