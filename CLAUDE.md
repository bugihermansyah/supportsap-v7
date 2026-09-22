# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Internal field-support management system for PT SAP ("supportsap-v7"), built on **Laravel 13 (v13.18) + Filament v5 (v5.6)**, PHP 8.3+. Both are recent majors whose APIs differ substantially from the versions most examples online target — see "Filament v5 idioms" below before writing any panel code. The admin panel is the whole app — `routes/web.php` is empty. Scaffolded from the Glow / `ercogx/laravel-filament-starter-kit` (see `README.md` for starter-kit setup steps), but the domain code is all custom.

Business domain: customer **Locations** own **Units** (hardware). Problems are logged as **Outstandings** (tickets), worked by support engineers who file **Reportings** (daily visit reports, scored as KPI). Broken units are swapped via **BorrowRequests** against **Warehouses**. Engineers belong to **Teams**, and most visibility is scoped by team.

Requirements documents (`PRD`, `PRD-support-performance.md`, `task.md`) are written in Indonesian, as are many inline comments. UI labels are mostly English.

## Commands

```bash
composer dev          # serve + queue:listen + vite concurrently (main dev loop)
composer check        # pint --dirty, php artisan test, phpstan (run before committing)

./vendor/bin/pint --dirty                        # format changed files (laravel preset)
./vendor/bin/phpstan analyse --memory-limit=2G   # larastan level 5, app/ only
php artisan test                                 # Pest
php artisan test --filter=SomeTest               # single test
php artisan test tests/Feature/ExampleTest.php   # single file

php artisan queue:listen --tries=1   # jobs (QUEUE_CONNECTION=database)
php artisan schedule:work            # runs email:fetch-status every 5 min
php artisan pail                     # tail logs
npm run build                        # production assets
```

Filament/Shield after adding a resource or policy:

```bash
php artisan shield:generate --all --ignore-existing-policies --panel=admin
php artisan filament:optimize-clear   # when navigation/discovery looks stale
```

## Critical constraints

- **Schema changes are made directly in MySQL, not through migrations.** This is the owner's deliberate workflow, not an accident. `database/migrations/` therefore holds only framework/plugin tables (users, cache, jobs, permissions, activity log, db_config, comments, exceptions) plus a handful of incremental `ALTER`s; the core domain tables — `outstandings`, `reportings`, `units`, `locations`, `borrow_requests`, `warehouses`, and the rest — exist only in the live database (`DB_CONNECTION=mysql`). Consequences:
  - **Never run `migrate:fresh` / `migrate:refresh`** — they will not rebuild the app, they will destroy it.
  - **Don't write a migration for a schema change unless asked.** When a task needs a new column or table, state the DDL you need and let the user apply it in MySQL, or ask first if they want a migration this time.
  - Never infer columns from migration files. Read the live schema: `php artisan db:table <table>`, or `php artisan tinker` + `Schema::getColumnListing('<table>')`. New models carry `@property` docblocks listing their real columns — keep those current, they are the only in-repo record of the schema.
  - DDL that has been handed over lives in `database/sql/*.sql` (plain scripts, run by hand). It is a record of what was asked for, not proof it was applied — verify against the live DB.
- **`php artisan test` is destructive unless `phpunit.xml` isolates the DB.** `tests/TestCase.php` uses `Plannr\...\FastRefreshDatabase` on the *base* class, so **every** test runs `migrate:fresh` on the active connection — dropping all 55 tables of the dev database and rebuilding only the ~18 framework ones. (`RefreshDatabase` being commented out in `tests/Pest.php` is a red herring; the trait on `TestCase` is what matters.) The `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` lines in `phpunit.xml` are the only thing keeping tests off the real database — never comment them out, and verify they are active before running any test. Recovery, if it happens anyway: Laragon keeps nightly full dumps in `C:\laragon\backup\mysql\`, and MySQL has `log_bin = ON` (`C:\laragon\data\mysql-8.4\binlog.*`) for point-in-time replay.
- `AppServiceProvider` calls `Model::unguard()` (no `$fillable` needed anywhere) **and** `Model::preventAccessingMissingAttributes()` — reading an attribute that was not selected or does not exist throws. Always select the columns you read, or eager-load.
- **Every `artisan` command needs MySQL running.** `AdminPanelProvider` resolves `->brandName(DbConfig::get('general.brand_name'))` at panel-registration time, so even `php artisan --version` throws `SQLSTATE[HY000] [2002]` when Laragon's MySQL is stopped. If artisan dies on a connection error, start MySQL — the code is probably fine.
- `User` uses `HasUuids`; most domain models use `HasUlids`. IDs are strings — type-hint accordingly.
- Timezone is hardcoded `Asia/Jakarta` in `config/app.php`.
- `DB::prohibitDestructiveCommands()` and `FilamentShield::prohibitDestructiveCommands()` are active in production.

## Filament v5 idioms

Filament v5 moved a lot of classes. Copying v3/v4 snippets will fatal. What this codebase actually uses:

| Concern | v5 (correct here) | Old namespace — do not use |
| --- | --- | --- |
| Form / infolist container | `Filament\Schemas\Schema`, methods typed `form(Schema $schema): Schema` | `Filament\Forms\Form`, `Filament\Infolists\Infolist` |
| Layout components | `Filament\Schemas\Components\{Section,Group,Fieldset,Tabs,Tabs\Tab,Wizard,Wizard\Step}` | `Filament\Forms\Components\Section`, etc. |
| Field components | `Filament\Forms\Components\*` (unchanged — `TextInput`, `Select`, `Repeater`, …) | — |
| State helpers | `Filament\Schemas\Components\Utilities\{Get,Set}` | `Filament\Forms\{Get,Set}` |
| **All** actions — page, table row, bulk | `Filament\Actions\{Action,CreateAction,EditAction,ViewAction,DeleteAction,BulkActionGroup,DeleteBulkAction}` | `Filament\Tables\Actions\*` (gone; zero uses in this repo) |
| Table / columns / filters | `Filament\Tables\{Table,Columns\*,Filters\*}` (unchanged) | — |
| Infolist entries | `Filament\Infolists\Components\*` (entries stay here; only the container moved) | — |
| Page view property | `protected string $view` — **not static** | `protected static string $view` |
| Navigation props | `protected static string\|BackedEnum\|null $navigationIcon` with the `Filament\Support\Icons\Heroicon` enum; `protected static string\|\UnitEnum\|null $navigationGroup` | plain `?string` |

Two stale `use Filament\Forms\Form;` imports remain in `Pages/UserKpiReport.php` and `Pages/SupportPerformance.php` — dead leftovers, the methods there already take `Schema`. Don't imitate them.

## Architecture

### Panel

Single Filament panel at `/` (`app/Providers/Filament/AdminPanelProvider.php`) with auto-discovery of `app/Filament/{Resources,Pages,Widgets}`. Navigation groups (declared in the provider, order matters): Work, Main, Support Reports, Borrow Reports, Administration. Many plugins are wired here — Shield (roles), Breezy (profile page plus custom `myProfileComponents` Livewire tabs), db-config, logger, exceptions, apex-charts, dicebear avatars, quick-create.

### Resource layout

Filament v5 convention, one directory per resource with the schema/table/pages split:

```
app/Filament/Resources/<Name>s/
  <Name>Resource.php      # model binding, navigation, canViewAny, getEloquentQuery, getPages
  Schemas/<Name>Form.php  # form schema
  Schemas/<Name>Infolist.php
  Tables/<Name>sTable.php
  Pages/{List,Create,Edit,View}<Name>.php
  RelationManagers/
```

Some resources are nested one level deeper under a **domain folder** (`Resources/Support/SupportReportings/`, `Resources/Preventive/PreventiveOutstandings/`). This is deliberate: the same Eloquent model gets several resources aimed at different roles (`PreventiveOutstandingResource` and `OutstandingResource` both wrap `Outstanding`; `SupportReportingResource` wraps `Reporting`). When adding a role-specific view of an existing model, follow that pattern rather than overloading one resource.

Custom pages beyond resources: `app/Filament/Pages/Reports/*` (~10 read-only report pages), `SupportPerformance.php`, `UserKpiReport.php`, `ScheduleDashboard.php` / `ScheduleCalendar.php`, plus settings pages. Widgets are grouped by audience in `app/Filament/Widgets/{Admin,HeadSupport,HeadPreventive,Preventif,Support,Report,SupportPerformance}`.

### Authorization

Two independent layers — check both when something is unexpectedly hidden:

1. **Shield / spatie-permission policies** (`app/Policies/*`, generated permissions per resource). `User::canAccessPanel()` additionally requires `status != 0`.
2. **Hardcoded role checks** in resources, pages and widgets: `canViewAny()` with `hasRole()` / `hasAnyRole()`, and `getEloquentQuery()` overrides that scope rows by team — e.g. `OutstandingResource` limits `head_support` / `support` to `whereHas('location', fn ($q) => $q->where('team_id', $user->team_id))`.

Role slugs in use: `super_admin`, `admin`, `owner`, `manager`, `head_support`, `support`, `helpdesk`, `head_preventive`, `preventive`.

### KPI scoring

`Reporting::calculateAutoScore()` is the heart of engineer performance. It runs automatically on `creating`, and on `updating` unless `score` was explicitly dirtied (manual evaluation overrides it). Rules: base 100, minus late-reporting penalties (H-1/H-2/H-3, with a 1-day grace when the outstanding's `level >= 4`), minus missing `attachments` / `form_support` media, plus a same-day-progress bonus, plus a difficulty bonus for Hard / Very Hard. Locations flagged `is_ho` short-circuit to 100.

Every threshold and penalty is a **runtime setting**, not a constant: read via `safe_db_config('general.kpi_*', $default)` (helper from `inerba/filament-db-config`, values stored in the `db_config` table) and edited on the `GeneralSettings` page. Grade bands live in `Reporting::getScoreGrade()` / `getScoreColor()`. `App\Livewire\KpiSimulator` and `SupportReportingResource::getEvaluationFormSchema()` mirror the same formula for what-if previews — **keep all of them in sync when changing scoring**.

### Background work

- `CalculateSupportTravelDistance` (queued): per `reporting_users` pivot row, chains each engineer's stops for a `date_visit` — origin is the previous reporting's location that day, else the engineer's `UserProfile` home coords — and calls the public **OSRM** router (`router.project-osrm.org`) to fill `distance` / `duration`. Short-circuits to 0 for `work = remote` and for locations with `area_status = 'out'`. Dispatched from `EditSupportReporting` and `Widgets/Support/Schedules`.
- `ScheduleMailJob` / `Mail\ScheduleMail`, and `Mail\SupportReportingMail` + the `ReportingEmail` model + the `SendEmailReporting` page: outbound reporting emails to customers.
- `email:fetch-status` (`FetchEmailUpdates`, scheduled every 5 minutes): reads unread IMAP mail from the logistics sender via `webklex/laravel-imap`, parses REQKRM/REQAMB numbers out of the body, and advances `BorrowRequest` status.

### Borrow request workflow

`BorrowRequestStatus` drives a long state machine (draft → submitted → approved → delivery_scheduled → delivered → pickup_scheduled → picked_up → waiting_return → partially_returned → returned, plus rejected / cancelled / retur), with `BorrowRequestLog` recording transitions and `BorrowRequestUnit` holding line items. `BorrowRequestType` distinguishes loan (15-day limit) / replacement / backup.

### Cascade deletes live in model `booted()`, not the DB

`Outstanding::booted()` deletes its reportings **one at a time** so that each `Reporting::booted()` fires and cleans up Spatie media plus `reporting_users`. Don't collapse these into mass `delete()` queries.

## Conventions

- Enums in `app/Enums` implement Filament's `HasLabel` / `HasColor` / `HasIcon` and are used directly in `casts()` — add new statuses there, never as raw strings.
- Global table defaults are set once in `AppServiceProvider::configureFilament()` (all columns toggleable, reorderable columns, pagination `[10, 25, 50]`); don't repeat them per table.
- Comments use `businesstilto/commentable` (`Commentable` / `Commenter` contracts); `Outstanding::getCommentMentionProviders()` wires `@` user mentions.
- Panel theme CSS is `resources/css/filament/admin/theme.css`, registered via `viteTheme()` — it must stay in `vite.config.js` inputs.
