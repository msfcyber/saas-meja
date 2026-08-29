---
paths:
  - 'app/Http/Controllers/**,app/Http/Requests/**,app/Policies/TenantResourcePolicy.php'
---

# Requests Policies

## Derive business ownership from tenant context
Operational controllers must derive tenant_id and outlet_id from TenantContext, never request input. TenantResourcePolicy requires both IDs to match the active context; normal scoped route binding provides the first line of outlet isolation.
