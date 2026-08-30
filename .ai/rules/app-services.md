---
paths:
  - 'app/Services/{MidtransPaymentGateway.php,MidtransWebhookService.php}'
---

# App Services

## Keep Midtrans references and callbacks isolated
Midtrans uses the globally unique `meja-payment-{payment_id}` provider reference. Start Snap only after the order transaction has committed, and validate native callbacks with SHA-512 of order ID, status code, gross amount, and server key before passing normalized events to PaymentWebhookService.
