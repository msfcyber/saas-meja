---
paths:
  - 'app/Models/**,database/migrations/**,config/permission.php'
---

# Migrations

## Tenant IDs are database-enforced
All outlet-owned business rows carry tenant_id. Use composite foreign keys containing tenant_id to reject cross-tenant relations at the database layer. Spatie Permission teams are enabled with tenant_id as the team foreign key; set the permission team context before role checks or assignments.
