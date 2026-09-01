# Browser E2E Testing

The repository includes a Playwright suite for the customer QR ordering flow.
It starts an isolated Laravel server backed by a temporary SQLite database and
uses a local Midtrans-compatible mock. No production database, payment
credential, or external gateway is needed.

## Run

Install the browser once on a new machine, then run the suite:

```sh
npx playwright install chromium
npm run test:e2e
```

The suite runs on desktop, tablet, and a 360 px mobile viewport. It covers QR
menu access, product selection, cart persistence, checkout, offline feedback,
the mocked paid webhook, tracking, receipt opening, horizontal overflow, and
serious/critical axe accessibility violations.
The performance budget is measured on a warm reload so one-time Laravel
process startup does not make the browser assertion nondeterministic.

The browser seeder is invoked explicitly by the test server and is not part of
the normal application seeder. Generated database and asset files are stored
under `storage/framework/testing`, which is ignored by Git.
