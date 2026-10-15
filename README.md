# Laundry Shop Inventory and Order Management System

A web-based system for a small laundry business, built as a learning project:
**React (Vite) + PHP (PDO/MySQLi) + MySQL, served locally with XAMPP.**

Staff and customers have completely separate logins, dashboards, and permissions.
Customers place orders, track laundry status in real time, view payments, and see
their pickup schedule. Staff manage customers, orders, the wash-to-pickup workflow,
payments, pickup scheduling, and reports.

> Inventory management is included in this build (stock levels, low-stock alerts,
> supply usage rules, and inventory transactions). The "What was removed" section
> below notes prior inventory scope/history.

---

## 1. Tech stack

| Layer | Technology |
|---|---|
| Frontend | React 18 (Vite), React Router |
| Backend | PHP 8 (PDO, prepared statements) |
| Database | MySQL (via XAMPP) |
| Local server | XAMPP (Apache + MySQL + phpMyAdmin) |
| Editor | Visual Studio Code |

No external frontend framework (no Tailwind, no component library) — all styling is
hand-written CSS in `frontend/src/index.css`.

---

## 2. Project structure

```
laundry-shop-system/
├── backend/
│   ├── config/
│   │   ├── database.php        # PDO connection (root / no password, XAMPP default)
│   │   ├── cors.php             # Locked to http://localhost:5173
│   │   ├── services.php         # Service names + price per kg
│   │   ├── loyalty.php          # Loyalty rules (stamps required, reward name, qualifying statuses)
│   │   ├── mail.php              # Mail configuration
│   │   ├── ordering.php         # Ordering/config defaults
│   │   └── pickup-slots.php      # Pickup slot definitions
│   ├── lib/
│   │   ├── Loyalty.php          # Shared loyalty logic (grant/redeem/progress)
│   │   └── Mailer.php           # Mail helper
│   ├── staff/                   # Staff auth, profile, staff management, dashboard
│   ├── customer/                # Customer auth, dashboard, orders, payments, pickup, profile, loyalty
│   ├── customers/               # Staff-side customer management (list/view/save/status)
│   ├── orders/                  # Order CRUD, load-status board, status transitions
│   ├── payments/                # Staff-side payment recording and history
│   ├── pickups/                 # Staff-side pickup scheduling (list/save/cancel/miss)
│   ├── loyalty/                 # Staff-side loyalty (list, redeem, backfill CLI)
│   ├── inventory/               # Inventory management (list/save/stock/rules/transactions)
│   ├── jobs/                    # Background jobs (pickup reminders)
│   ├── reports/                 # Sales, orders, customer transactions, payment history, inventory usage
│   └── register.php             # Shared registration entry
│
├── database/
│   └── loyalty-system.sql       # Loyalty tables schema
│
└── frontend/
    └── src/
        ├── components/            # Shared UI: layouts, modals, badges, trackers
        ├── pages/
        │   ├── public/            # Landing page
        │   ├── staff/             # All staff screens (including Loyalty)
        │   └── customer/          # All customer screens (including Loyalty)
        ├── services/api.js        # fetch() wrapper, sends session cookie
        ├── utils/orderStatus.js   # Formatters, status labels/steps
        ├── App.jsx                # All routes
        └── index.css              # All styling
```

---

## 3. Setup from scratch

1. Install **XAMPP** (Apache + MySQL + PHP + phpMyAdmin) and **Node.js LTS**.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. In phpMyAdmin, create the database `laundry_shop_db` and run the table-creation
   SQL (customers, staff, orders, payments, pickup_schedules — see your project's
   SQL history / phpMyAdmin export for the exact schema).
4. Place the project at `C:\xampp\htdocs\laundry-shop-system`.
5. In `frontend/`, run:
   ```
   npm install
   npm run dev
   ```
6. Confirm the terminal shows `http://localhost:5173/` — this exact address is
   required, since `backend/config/cors.php` only allows that origin.
7. Create the first staff (owner) account directly in phpMyAdmin's `staff` table,
   with a `password_hash()`-generated value in the `password` column (or use a
   one-time setup script, then delete it immediately after running it).

---

## 4. Test / default accounts

| Role | Username | Password |
|---|---|---|
| Shop Owner | `owner` | `Owner@12345` |
| Laundry Staff | `staff1` | `Staff@12345` |

**Change these before any real use.** They were created for development only.
Customer accounts are self-registered at `/customer-register`.

---

## 5. Core workflow

```
Customer places order (staff-entered, drop-off)
         ↓
Weigh & Log Load          (staff enters weight → price auto-calculated)
         ↓
Washing → Drying → Folding   (staff moves the load forward, one step at a time)
         ↓
Ready for Pickup           (requires full payment before it can move further)
         ↓
Payment                    (Cash / GCash / Other, partial payments allowed)
         ↓
Pickup scheduled            (staff sets date + time slot)
         ↓
Completed                   (order marked done, linked pickup auto-completes)
```

Customers see this same progression live on their **Order Status** page.
Only the shop owner can move a load *backward* (to correct a mistake), and only
before it reaches "Ready for Pickup."

### Loyalty
- 1 completed wash = 1 loyalty stamp (only counts when order reaches `ready_for_pickup` or `completed`).
- 10 stamps = 1 promotional reward (`Free Wash & Fold` by default; configurable via `config/loyalty.php`).
- When a reward is unlocked, the card stays at 10/10 until staff redeems it (customer claims reward); redemption resets stamps to 0 for the next cycle.
- Each order gives at most 1 stamp (enforced by unique `loyalty_stamps.order_id`).
- See `backend/lib/Loyalty.php` and `backend/loyalty/backfill.php` for the single-source implementation.

---

## 6. Roles and permissions

| Action | Shop Owner | Laundry Staff |
|---|---|---|
| View customers | ✅ | ✅ |
| Add / edit / deactivate customers | ✅ | ❌ |
| Create / edit orders | ✅ | ✅ |
| Cancel orders | ✅ | ❌ |
| Move a load forward | ✅ | ✅ |
| Move a load backward | ✅ | ❌ |
| Record payments | ✅ | ✅ |
| Schedule / reschedule / cancel pickups | ✅ | ✅ |
| View/Manage Loyalty (list, redeem) | ✅ | ✅ |
| View Reports | ✅ | ❌ |
| Manage staff accounts | ✅ | ❌ |
| Edit own name / password | ✅ | ✅ |
| Manage inventory (stock, rules, transactions) | ✅ | ✅* |

Every permission is enforced **twice**: the React UI hides buttons the role
shouldn't see, and every PHP endpoint independently checks `requireStaff()` or
`requireOwner()` — the UI hiding a button is a convenience, not the security
boundary.

---

## 7. Security notes

- Passwords are hashed with `password_hash()` and checked with `password_verify()`
  — never stored or compared as plain text.
- All SQL uses PDO **prepared statements**; no string-concatenated queries.
- Customer-facing endpoints always read the logged-in customer's ID from the
  **session**, never from the request — a customer cannot ask for another
  customer's data no matter what they send.
- CORS (`backend/config/cors.php`) is locked to `http://localhost:5173`. If you
  ever deploy this off of XAMPP, update `$allowed_origins` to your real domain.
- Session cookies are set `HttpOnly` and `SameSite=Lax`.
- The shop owner cannot deactivate or demote their own account, so the system
  can never lock itself out of the owner-only pages.

**Still open (not needed for local XAMPP development, required before any real
public deployment):**
- HTTPS (XAMPP serves plain HTTP locally)
- A production web server config (Apache `.htaccess` / hardened `php.ini`)
- Rate limiting on login endpoints
- A proper backup strategy for the MySQL database

---

---

## 9. Known limitations

- No email notifications (order status changes, pickup reminders) — everything
  is pull-based; customers check the site themselves.
- No automated tests; Step 27's manual walkthrough is the current test coverage.
- Reports are read-only in the browser — no PDF/CSV export yet.
- Order numbers (`ORD-1001`, `ORD-1002`, ...) are sequential and predictable;
  fine for a single small shop, would need a different scheme for multi-branch use.

---

## 10. Where to go from here

- Add automated tests (PHPUnit for the backend, React Testing Library for the
  frontend) if this grows beyond a single-shop tool.
- Add a `.env`-based config instead of hard-coded database credentials before
  ever deploying outside of local development.
- Wire automatic inventory deduction if supply-cost tracking is desired (using `inventory_transactions` and `supply_usage_rules`).
