---
paths:
  - 'app/Events/**,app/Services/OrderStatusService.php,routes/channels.php'
---

# Services

## Order realtime channel isolation
Dispatch OrderStatusUpdated only after successful transaction commits. Staff consume the private outlet channel authorized against active tenant/outlet plus `order.view`; guest tracking consumes an unguessable access-token-hash public channel and retains polling as fallback.
