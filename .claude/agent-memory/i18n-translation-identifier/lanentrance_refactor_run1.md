---
name: LanEntrance refactor run 1
description: Files modified, string counts, flags, cross-app shared string candidates from LanEntrance i18n refactor (invocation 2/5)
type: project
---

**Run date**: 2026-04-23

## Files Modified (strings converted per file)

| File | Strings converted |
|------|-------------------|
| `resources/js/locales/en.json` | +~120 new keys added |
| `pages/settings/Profile.vue` | 9 |
| `pages/settings/Appearance.vue` | 4 |
| `pages/settings/Security.vue` | 14 |
| `components/DeleteUser.vue` | 8 |
| `components/UserMenuContent.vue` | 2 |
| `components/AppearanceTabs.vue` | 3 (Light/Dark/System) |
| `components/TwoFactorRecoveryCodes.vue` | 5 |
| `components/TwoFactorSetupModal.vue` | 8 |
| `components/AlertError.vue` | 1 (default title) |
| `components/announcements/AnnouncementBanner.vue` | 1 |
| `components/NavMain.vue` | 1 ("Platform" label) |
| `components/AppHeader.vue` | 2 ("Dashboard" nav, "Navigation menu" sr-only) |
| `components/entrance/DecisionDisplay.vue` | 15 (all decision state labels + action buttons) |
| `components/entrance/DegradedBanner.vue` | 1 |
| `components/entrance/LookupForm.vue` | 6 |
| `components/entrance/OverrideModal.vue` | 5 |
| `components/entrance/QrScanner.vue` | 8 (all camera error messages + fallback) |
| `components/entrance/QueuedScans.vue` | 4 |
| `components/entrance/TokenEntry.vue` | 3 |
| `pages/entrance/Scanner.vue` | 2 (head title, manual lookup link) |
| `pages/entrance/Lookup.vue` | 2 (head title, back to scanner link) |
| `pages/entrance/Analytics.vue` | 8 (all stat card labels + error) |
| `pages/auth/Login.vue` | 7 |
| `pages/auth/Register.vue` | 8 |
| `pages/auth/ForgotPassword.vue` | 5 |
| `pages/auth/ResetPassword.vue` | 6 |
| `pages/auth/TwoFactorChallenge.vue` | 5 |
| `pages/auth/ConfirmPassword.vue` | 4 |
| `pages/auth/VerifyEmail.vue` | 4 |

**Total strings converted**: ~141

## Cross-App Shared String Candidates (flag for centralization)

These strings appear in LanEntrance and likely also in LanBrackets (already flagged there) and other Lan* apps:
- `"Log out"` / `userMenu.logout` — identical across all apps
- `"Settings"` / `userMenu.settings` — identical across all apps
- `"Profile"` — navigation item
- `"Dashboard"` — navigation item
- `"Save"` / `"Saved."` / `"Cancel"` — universal form actions
- `"Password"` / `"Email address"` — universal field labels
- `"Remember me"` / `"Forgot password?"` — login form labels
- `"Something went wrong."` — generic error title
- `"Dismiss"` — announcement/banner action
- `"Platform"` — sidebar group label (NavMain)
- `"Confirm"` / `"Cancel"` — dialog footer buttons
- `"Light"` / `"Dark"` / `"System"` — appearance theme labels
- `"Navigation menu"` (sr-only) — accessibility label for mobile nav

## Flagged — Not Translated

- `AppHeader.vue` — `rightNavItems`: `"Repository"` (GitHub link) and `"Documentation"` (Laravel docs link) — these are dev-scaffolding links from the Laravel starter kit, not app content. Ambiguous: they appear in the UI as icon tooltips but are technical/external references. Leaving for human review.
- `AppLogo.vue` — "LanEntrance" app name text — technically translatable (could rebrand) but usually treated as a proper noun/product name. Skipped.
- `defineOptions layout.title/description` — Some auth pages use plain string keys like `'auth.login.layoutTitle'` (already a key reference) vs static text. The layout system may interpolate these differently. Flagged: these strings in `defineOptions` are passed to a parent layout, not directly rendered — translation there depends on how the auth layout resolves them. Left as key strings; the auth layout would need to call `$t()` on them if it renders them.

## Orphaned/Broken Existing Keys Noticed

- `auth.verifyEmail.linkSent` existed as `"A new verification link has been sent to your email address."` in the pre-existing en.json. The `VerifyEmail.vue` page had a slightly different hardcoded version: `"A new verification link has been sent to the email address you provided during registration."` — these differ. The pre-existing key was reused (shorter version). The longer version appears in `Profile.vue` too but as a separate key `settings.profile.verificationLinkSent`. Flag: these two "verification link sent" messages carry slightly different context (registration vs. profile update) — consider whether they should be one key or two.

**Why:** Institutional memory for the next agent invocation (LanHelp, LanShout, LanBrackets, LanCore).
**How to apply:** When working on shared string centralization, use this list as the seed set.
