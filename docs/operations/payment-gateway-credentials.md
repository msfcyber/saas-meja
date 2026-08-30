# Payment gateway credentials

`MIDTRANS_SERVER_KEY` is the platform-managed Midtrans key for SaaS subscription
invoices and their notifications. It is not a fallback for tenant order
payments.

Tenant owners manage the Midtrans Server Key at `Settings > Gateway`. The key is
encrypted at rest, stored as a versioned credential, and never returned to the
browser or written to audit metadata. Starting or reconciling an order binds its
payment to the current tenant credential. Retired versions remain available so
notifications for already-started payments can still be verified with the exact
credential used for that payment.
