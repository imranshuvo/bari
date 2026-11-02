# House Cost Planner — v1 Design

Date: 2026-11-02
Status: agreed in chat; the user asked to build straight away and review the running app.

## Goal

A simple web app where a team manages the costs of building a house.
UX is the highest priority: anyone should understand every screen without explanation.

Out of scope for v1 (possible later): builder estimating / quotes to clients, change orders,
loan drawdowns, cash-flow forecasting, handover & defects, splitting one bill across categories,
partial payments, exports.

## Stack

- Laravel 13, Filament 5 (Livewire 4), PostgreSQL.
- Filament tenancy: each **Project** is a tenant with its own team.
- Languages: English and Bangla (per-user choice). Numbers in Bangla use Bangla digits and
  lakh grouping (১,৮৫,০০০).

## Concepts the user sees

**Budget → Agreed → Spent**

| Concept | Meaning |
|---|---|
| Budget | Project total budget + categories (Foundation, Roof, ...) each with an optional budget |
| Agreed | A price agreed with a supplier for a category (optional) |
| Spent | Every bill and receipt: amount incl. VAT, date, paid or unpaid (with due date), photo/PDF |
| Suppliers | Created automatically by typing a name when adding a cost |

## Roles (per project)

| Role | Can do |
|---|---|
| Owner | Everything: settings, budget/categories, team, delete anything |
| Editor | Add/edit costs, agreements, suppliers; delete only costs they created themselves |
| Viewer | Read only |

Owners invite by email + role. Invitation produces a shareable link; if the email already
has an account the person is added immediately. Pending invitations are accepted on sign-up
or by opening the link.

## Data

- `projects`: name, address, currency (ISO), vat_rate, total_budget, start_date, end_date
- `project_user` (Membership): project, user, role
- `project_invitations`: project, email, role, token, invited_by, accepted_at
- `categories`: project, name, budget, sort
- `suppliers`: project, name (unique per project), contact person, phone, email, trade, notes
- `agreements`: project, supplier, category, title, amount, agreed_on, notes, attachments
- `costs`: project, category, supplier?, agreement?, description, reference, amount, vat_rate,
  vat_amount, date, is_paid, paid_on, due_date, attachments, created_by
- `activities`: project, user, event, subject, description, changes
- `users.locale`

All money is stored as integer minor units (øre / paisa). The amount entered is what you pay
(incl. VAT); the VAT part is calculated from the rate and stored for the accountant.

## Calculations

- **Paid so far** = sum of paid costs
- **Billed** = sum of all costs
- **Expected total** = for each agreement, max(agreed amount, billed against it)
  + all costs not linked to an agreement
- **Left in budget** = budget − expected total (red when negative)

The same figures are calculated per category against the category budget.

## Screens

Top bar: project switcher, **+ Add cost** (slide-over, available everywhere for owners/editors).

1. **Overview**: Paid so far / Expected total / Left in budget, category progress bars,
   bills due soon (overdue highlighted).
2. **Spent**: list of costs with quick filters (Unpaid, This month, category, supplier),
   one-tap "Mark paid".
3. **Budget**: categories with Budget / Agreed / Spent / Left + bar; total budget and
   "not yet split" hint.
4. **Agreed**: agreements with "billed so far X of Y".
5. **Suppliers**: auto-built list with totals.
6. **Team** (owners manage, others see), **Activity** log, **Project settings** (owners).

Add cost form order: amount (autofocus) → photo/PDF → supplier (type-ahead, auto-created) →
category (pre-filled from the supplier's last cost) → date (today) → Paid? (if no: due date) →
agreement auto-linked when exactly one matches supplier + category.

First-time setup: one form with name/address, currency + VAT rate, total budget, and a
category template (all ticked by default).

## Testing

Feature tests (PHPUnit, Postgres test DB) for: finance calculations, money parsing/formatting
(incl. Bangla digits), role permissions and tenant isolation, cost creation via Filament,
invitations.
