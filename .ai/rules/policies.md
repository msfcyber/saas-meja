---
paths:
  - 'app/Models/**,app/Http/Middleware/**,app/Policies/**'
---

# Policies

## Resolve tenant context before bindings
ResolveTenantContext must run before SubstituteBindings, set Spatie's tenant team ID, and clear both context and permission team state after each request. Tenant/outlet global scopes only activate when TenantContext is marked resolved; request contexts with no tenant return no business rows, while CLI/factory setup remains unscoped.
