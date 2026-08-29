---
paths:
  - 'app/**/Public*.php'
---

# App

## Re-validate public access and remove scopes explicitly
Guest requests resolve no tenant context, so tenant/outlet global scopes can intentionally produce `1 = 0`. Public order reads and nested relation loads must use `withoutGlobalScopes()`, then explicitly validate QR, tenant, outlet, and table ownership inside the service/transaction.
