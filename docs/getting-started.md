# Getting Started

::: info Audience
Site administrators setting up Verified Elements for their team. Editors can skip ahead to [Core Concepts](core-concepts.md).
:::

## What Verified Elements does

- Adds a "Verified until" date and a "Reviewer" to entries and assets, per site.
- Marks each item as **Verified** or **Expired** based on that date.
- Surfaces items that need attention: in a dedicated control panel section, in dashboard widgets, on each user's account page, and by email.
- Lets you verify or assign many items at once with bulk actions.

## Requirements and installation

The plugin requires Craft CMS 5.6 or later. Installation happens through the Craft Plugin Store or Composer and is typically done by your developer. See the [repository README](https://github.com/webhubworks/craft-verified-elements#installation) for the exact steps.

Plugin Store: [Craft Verified Elements](https://plugins.craftcms.com/verified-elements).

## First-time setup checklist

Work through these steps once after installation:

1. **Choose your edition.** The free Standard edition covers entries on a single site, the paid Pro edition adds multi-site support and asset verification. See [Permissions and Editions](permissions-and-editions.md).
2. **Enable the sections you want to track.** Go to **Verified Elements → Settings → Entries** and switch on each section that should be part of the review workflow. See [Settings](settings.md).
3. **Set a default Reviewer and default period per section.** New content in an enabled section automatically receives these defaults. Without a default Reviewer, expired items go unnotified (the settings screen warns you about this).
4. **Enable volumes (Pro only).** Go to **Verified Elements → Settings → Assets** and repeat the same setup for asset volumes.
5. **Grant permissions.** In Craft's user group settings, grant "Access Verified Elements", "Verify entries" / "Verify assets", and "Manage Verification Settings" as appropriate. See [Permissions and Editions](permissions-and-editions.md).
6. **Add the dashboard widgets.** Each user can add "Elements to Review" and "Verification Health" to their Craft dashboard. See [Dashboard Widgets](dashboard-widgets.md).
7. **Make sure the expiry check runs.** Reviewer email digests are sent when the plugin's scheduled check runs. Ask your developer to schedule it (see [Email Notifications](email-notifications.md)).

## A typical workflow at a glance

1. An editor saves an entry in an enabled section. The section's default period and default Reviewer are applied automatically.
2. Time passes. The "Verified until" date arrives and the entry becomes **Expired**.
3. The Reviewer receives an email digest and sees the entry in their review queue and dashboard widget.
4. The Reviewer checks the content, updates it if needed, and sets a new "Verified until" date. The entry is **Verified** again.
