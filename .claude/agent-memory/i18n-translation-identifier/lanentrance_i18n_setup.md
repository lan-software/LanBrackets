---
name: LanEntrance i18n setup
description: Translation library, locale file location, key conventions for LanEntrance
type: project
---

**Translation library**: vue-i18n v9 (composition API — `useI18n().t()` / `$t()` in templates)

**Locale files**: `/home/mawiguko/git/lan-software/LanEntrance/resources/js/locales/` — 10 JSON files (en, de, fr, es, ko, sv, uk, nds, sxu, tlh)

**PHP lang files**: `/home/mawiguko/git/lan-software/LanEntrance/lang/{locale}/validation.php` only — no `__()` usage yet in PHP layer.

**Key naming convention**: Hierarchical, snake_case within camelCase namespace segments. Examples:
- `auth.login.emailPlaceholder`
- `entrance.decision.confirmAndCheckIn`
- `settings.deleteAccount.dialogDescription`
- `twoFactor.recoveryCodes.viewButton`

**Namespace groupings established**:
- `common.*` — universal labels (save, cancel, delete, etc.)
- `auth.*` — login, register, forgotPassword, resetPassword, twoFactor, verifyEmail, confirmPassword, ssoStatus
- `settings.*` — profile, appearance, security, deleteAccount, language
- `navigation.*` — sidebar nav labels (dashboard, scanner, lookup, analytics, platform)
- `landing.*` — welcome page
- `dashboard.*` — dashboard page
- `entrance.*` — scanner, lookup, analytics, events, decision, override, tokenEntry, qrScanner, queuedScans, degraded
- `twoFactor.*` — recoveryCodes, setup
- `announcements.*` — dismiss
- `userMenu.*` — settings, logout
- `navigation_menu.*` — navigationMenu (sr-only)
- `errors.*` — somethingWentWrong
- `validation.*` — frontend field validation messages

**Why:** LanEntrance uses standard Inertia+Vue3 with vue-i18n. AppSidebar.vue already used `useI18n()` as a pattern reference before the refactor.
**How to apply:** Always use `const { t } = useI18n()` in script-setup and `$t()` in templates. Only add to en.json — other 9 locales not touched.
