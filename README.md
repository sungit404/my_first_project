# StayHaus Boarding Rental System (v2)

Purple-and-black boarding house rental system built from the **System Design document**
(Administrator / Landlord / Tenant, room approval, bookings, payments, reports, logs).

## 1. Run it on Laravel Herd (parang jukebox: ilagay mo lang sa tamang slot)
1. Unzip, then move the `stayhaus` folder into your Herd sites folder (e.g. `~/Herd/stayhaus`).
2. Open `http://stayhaus.test`. The database (`data/stayhaus.sqlite`) is created and filled with sample data on first load.
3. Want a fresh start? Delete `data/stayhaus.sqlite` and reload.

No `composer install`, no `.env`, no migrations to run. Requires PHP 8.1+ with `pdo_sqlite` (Herd has it).
`data/` and `assets/images/uploads/` must be writable.

## 2. Demo accounts
| Role | Email | Password |
|---|---|---|
| Administrator | admin@stayhaus.test | admin123 |
| Landlord | landlord@stayhaus.test (or landlord2@...) | landlord123 |
| Tenant | bea@example.com (also jero@, nica@, carlo@) | tenant123 |

## 3. Try this flow (5 minutes)
1. **Landlord** → My Rooms → *Add Room* → add photos → *Submit for approval*.
2. **Admin** → Approvals → *Approve* (or *Reject* with a reason). Approved rooms go public.
3. **Tenant** → Find a Room (filter by price, type, amenities, dates) → *Request booking*. The price summary shows base rate + amenity extras + deposit.
4. **Landlord** → Booking Requests → *Confirm*. The room turns **occupied** on the start date; competing requests for the same dates are declined automatically.
5. **Landlord** → record a payment. **Admin** → Reports → export CSV. **Admin** → Activity Logs shows everything.

## 4. What matches the PDF
Roles/RBAC · approval status separate from room status · private/shared + capacity · amenities with optional extra price ·
multiple room images · search & filter (budget, type, capacity, occupancy, amenities, dates, sort, pagination) ·
compare rooms · booking conflict prevention · manual payment records · notifications · reports with date filters + admin CSV ·
activity/audit logs · settings · tenant privacy (occupant names never public) · no check-in/out, no maintenance module.

Tables: `roles, users, password_reset_tokens, room_types, rooms, room_images, amenities, amenity_room, room_approval_logs, activity_logs, bookings, payments, notifications, settings`
(same names and fields as the data dictionary; see `database/schema.sql`).

## 5. Honest differences from the PDF (tell your instructor or update the paper)
- **Plain PHP + SQLite, not Laravel + MySQL.** ENUM = TEXT + CHECK, DECIMAL(10,2) kept as declared type. Table/field names are the same.
  `notifications` also has `notifiable_id` (Laravel stores it too).
- **Email verification is not implemented** (`email_verified_at` exists but nothing sends mail). **Password reset** works, but with no mail server the link is shown on screen.
- **Users are deactivated, not deleted**, to protect booking/payment history.
- A room must be **available** (approved, published, not occupied today) to be booked. Total = (base rate + amenity extras) x 30-day months + deposit.
- Editing an approved room as a landlord sends it back for re-approval.

## 6. Folder tour
`index.php rooms.php room.php compare.php login.php register.php` public site · `panel/` all logged-in pages (one set of pages, behaviour changes by role) ·
`includes/` helpers, auth, layouts · `database/` schema + seed · `assets/` CSS, JS, room images.
