# Repair validation

The branch retains the PHP/MySQL application and existing MAE identity. It repairs account access, order handling, management pages, uploads, setup, and the interface described in the repair plan.

## Automated checks

The final local run passed **279 assertions** using the PHP HTTP server and MariaDB 10.11.14. The suite provisions isolated test databases and removes them afterward. It covers:

- PHP syntax, fresh setup, original-schema migration, repeated migration, duplicate-identity preflight, and version 1 → 2 upgrades.
- Registration, login/logout, admin/customer separation, CSRF, direct route access, and customer ownership of orders.
- Database-derived prices and product names, whole-number quantities, cart continuity, simultaneous sessions, and independent database writers for different customers.
- Immutable paid/cancelled orders, guarded invoice reopening, request-time/scheduled expiry, and linked cancellation history.
- Product/customer/admin management, archival, deactivation/reactivation, retained paid history, and deletion restrictions.
- Validated image uploads and prevention of legacy PHP upload execution in the development router.
- Profile City changes, blank-password preservation, retained form values after errors, and escaped product/profile content.
- Public highlights using current catalog prices and excluding archived products.

JavaScript syntax and Git whitespace checks also passed. Selected shared colors were checked numerically: gold-button text 9.93:1, secondary text on the page background 5.86:1, and unpaid-invoice status text 7.26:1. These checks do not establish full WCAG conformance.

## Visual checks

Read-only HTML snapshots rendered by the actual PHP views were inspected in Chrome. The checks included the landing page, product preview modal, catalog, profile, active orders, and product administration. The catalog was also rendered at 320px, 390px, 768px, and 1440px widths. Narrow-screen measurements showed no page-wide horizontal overflow, and mobile navigation exposed search correctly.

The live managed browser preview could not launch PHP. Layout and JavaScript checks therefore used rendered snapshots; actual form submissions, permissions, and database changes were verified through the HTTP regression suite, rather than browser form submission.

## Implementation decisions

- Customer accounts with orders are deactivated rather than deleting their history. Only accounts without orders can be permanently removed.
- Adding the first item still opens an invoice and starts its 24-hour window. Separate checkout/payment processing is outside this repair.
- Deadlocks/lock timeouts have bounded retries only for database-only order transactions. Upload callbacks are never replayed.
- Migration column additions use explicit COPY rebuilds after a test exposed inconsistent instant-column metadata on the older MariaDB runtime. Use a backup and maintenance window as described in the README.
- Existing product photos, the M-A-E logo, gold/charcoal palette, Montserrat/Roboto Slab typography, and promotional voice remain. The storefront and landing page now share compatible Bootstrap assets.
