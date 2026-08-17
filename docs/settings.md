# Settings

::: info Audience
Administrators and users with the "Manage Verification Settings" permission. The settings live at **Verified Elements → Settings** in the control panel.
:::

The settings control **which content is tracked** and **what defaults new content receives**. There are up to three pages: Entries, Assets (Pro), and Subscription Plan.

## Entries

A table of all sections in your installation. Per section you configure:

- **Enabled**. Switch verification on or off for this section. Only enabled sections show the Verification panel, appear in the plugin's listings, and send notifications.
- **Default Reviewer**. The user assigned automatically to new entries in this section (when the entry gets a verification date and has no Reviewer yet). If a section is enabled without a default Reviewer, the page warns you: expired entries without a Reviewer are only reported to the system email address, not to a person.
- **Default Period**. The "Verified until" period applied automatically the first time an entry is saved: 7 days, 30 days, 90 days, 1 year, or Indefinitely. Editors can always override it per entry.

**Multi-site (Pro):** the page has one tab per site. Each site has its own enablement and defaults, so a section can be tracked on one site with different Reviewers than on another. Remember to save each site's tab separately.

TODO: Screenshot of the Entries settings table with the site tabs.

## Assets (Pro)

The same table for asset volumes: Enabled, Default Reviewer, Default Period per volume. Volumes are not site-specific in Craft, so these settings apply to all sites at once, there are no site tabs.

TODO: Screenshot of the Assets settings table.

## Subscription Plan

Shows the two editions (Standard and Pro) side by side, marks your current plan, and links to the Craft Plugin Store to upgrade or change. See [Permissions and Editions](permissions-and-editions.md) for what each edition includes.

TODO: Screenshot of the Subscription Plan page. Replace the placeholder store link once the plugin is published.

## Good to know

- Defaults only apply to **new** content. Changing a section's default period does not rewrite dates on existing entries; use the **Verify** bulk action for that.
- Disabling a section or volume hides its verification UI and stops its notifications. Already stored verification data is kept. TODO: verify and document exactly what happens to existing data and assignments when a section is disabled and re-enabled.
- When a new site is created, asset volume settings are carried over to it automatically. Entry sections start unconfigured on the new site and need to be enabled on their site tab.
