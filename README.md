# TIF PMO · WBS Tracker (Laravel)

The Project Management Office app of PT Telkom Infrastruktur Indonesia (Infranexia) for tracking the
WBS of application projects. TIF admins create projects, implementation partners and their accounts;
partner staff (Project Manager, Project Admin, Business Analyst) update progress and upload evidence; the app works out whether each
deliverable and the project as a whole is LAGGED or ON SCHEDULE.

This package holds the application code, not a full Laravel install. Copy it into a new Laravel project.

## Requirements

- PHP 8.2+ with the `zip`, `xml`, `gd` and `fileinfo` extensions
- Composer
- Laravel 11 or newer
- Database: MySQL/MariaDB, PostgreSQL or SQLite

## Setup

```bash
# 1. Create a new Laravel project
composer create-project laravel/laravel wbs-tracker
cd wbs-tracker

# 2. Copy this package into the project folder (overwrite matching files)
#    Stock Laravel files that get replaced: app/Models/User.php and routes/web.php
cp -R /path/to/wbs-tracker-laravel/. .

# 3. Install the Excel reader
composer require phpoffice/phpspreadsheet

# 4. Edit .env: database connection, then the first admin account
#    ADMIN_USERNAME=admin
#    ADMIN_PASSWORD=a-strong-password
#    APP_TIMEZONE=Asia/Jakarta

# 5. Create the tables and the admin account
php artisan migrate
php artisan db:seed --class=AdminSeeder

# 6. Run
php artisan serve
```

Open http://localhost:8000 and sign in with the admin account from `.env`.
Remove `ADMIN_PASSWORD` from `.env` once the admin exists.

For evidence uploads up to 20 MB, set `upload_max_filesize` and `post_max_size` in `php.ini` to at
least 25M (and `client_max_body_size` when running behind Nginx).

## Data model

```
Project ──< Deliverable ──< Activity (WBS step) ──< Evidence
   └── partner (User with role "partner")
```

- A project holds one or more **deliverables**.
- Every deliverable gets its own copy of the full WBS, so each step is tracked once per deliverable:
  its own progress, actual dates, notes and evidence.
- The baseline (weights and plan dates) starts identical for all deliverables and can then be adjusted
  per deliverable and step by an admin.

## How it is used

1. **Admin › Partners**: add the partner company, then open it to add accounts for its people with a
   role (Project Manager, Project Admin or Business Analyst). The app generates a one-time password,
   shown once; the person sets their own at first sign-in. Passwords are reset on the same page.
2. **Admin › WBS items**: the standard steps new deliverables start from. Each step is **mandatory**
   (always included) or **optional** (can be left out per deliverable when creating a project).
3. **Admin › New project**: enter the project details, pick the partner, then add deliverables. Each
   deliverable gets its own WBS tab ("Add deliverable" adds a tab):
   - **Plan each deliverable here**: the configured WBS items with weights and plan dates per tab;
     optional steps can be unticked. Dates can be filled from a start date, or copied from the first
     deliverable.
   - **Upload a WBS file (Excel)**: one file's steps, weights and dates are used for every deliverable.
4. **Partner staff**: sign in, open a project, open a deliverable, then click a step to update progress,
   actual dates and notes and to upload evidence. They only see their own company's projects.
5. **TIF admins only** change the baseline (weights and plan dates) per step, add or delete deliverables,
   and delete projects.
   A deliverable added later copies the baseline of the project's first deliverable.

## Excel format

First sheet, with this header row:

| No | Nama Activity | Bobot | Sirkulir | Tg. Plan Start | Tg. Plan Finish | Evidence |

- Rows numbered with whole numbers (1, 2, 3, 4) are phases; rows numbered with decimals (1.1, 1.2, …) are activities.
- Plan Start and Plan Finish must be date cells.
- Rows above the header (project name, contract number, value) are ignored; project details come from the form.
- Activity numbers are regenerated from the row order.

## Status rules

- **Plan to date** (per deliverable) = sum of weight × the share of each step's planned duration that has elapsed (linear per day).
- **Actual** (per deliverable) = sum of weight × step progress.
- A deliverable is **LAGGED** when actual < plan, otherwise **ON SCHEDULE**; **COMPLETED** when every step is at 100%.
- **Project** plan and actual are the simple average of its deliverables (each deliverable counts equally),
  and the same LAGGED / ON SCHEDULE rule is applied to those averages.
- Per step: Overdue (past plan finish and not 100%), Behind (below the planned progress), In progress, Not started, Done.

The logic lives in `app/Models/Concerns/HasScheduleStatus.php`, `app/Models/Deliverable.php` and `app/Models/Activity.php`.

## Files

```
app/Http/Controllers/        AuthController, ProjectController, DeliverableController, ActivityController, EvidenceController
app/Http/Controllers/Admin/  ProjectController, DeliverableController, PartnerController
app/Http/Middleware/         EnsureRole (guards the admin pages)
app/Models/                  User, Project, Deliverable, Activity, Evidence
app/Services/WbsTemplate.php standard WBS + Excel reader
database/migrations/         role/username columns on users; projects, deliverables, activities, evidences tables
database/seeders/            AdminSeeder
resources/views/             layout, sign-in, project list, project overview, deliverable WBS, project form, partners
routes/web.php
```

Activity names, circulation and required-evidence texts in the standard WBS are kept exactly as written
in the source template (some are in Indonesian); edit them in `app/Services/WbsTemplate.php` if you want
them translated.

Evidence files are stored on the `local` disk (`storage/app/private/evidence/{project}/{deliverable}/{activity}`)
and can only be downloaded through the app by an admin or the project's partner.
# wbs-tracker
