---
paths:
  - 'app/Http/Controllers/PublicMenuController.php,app/Http/Controllers/TableQrCodeController.php,app/Services/TableQrCodeService.php,routes/web.php,app/Models/TableQrToken.php'
---

# Models

## Resolve public QR outside tenant context
Public QR routes run without an authenticated TenantContext. Query token and catalog records with withoutGlobalScopes(), then validate tenant/outlet/table ownership and active status explicitly. Store only a SHA-256 token hash; persist the generated SVG artifact for download/print, and revoke/delete the old artifact on rotation or revocation.
