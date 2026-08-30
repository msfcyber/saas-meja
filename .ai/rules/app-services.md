---
paths:
  - 'app/Services/{MidtransPaymentGateway.php,MidtransWebhookService.php,PaymentGatewayCredentialService.php}'
---

# App Services

## Keep Midtrans references and callbacks isolated
Midtrans uses the globally unique `meja-payment-{payment_id}` provider reference. Start Snap only after the order transaction has committed. Order checkout, reconciliation, and native callbacks must use the payment's bound tenant credential, including retired versions; subscription invoices and callbacks remain platform-managed through `payments.midtrans.server_key`. Validate native callbacks with SHA-512 of order ID, status code, gross amount, and the selected credential before passing normalized events to PaymentWebhookService.
