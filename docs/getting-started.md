# Getting Started

::: info Audience
Site administrators setting up Verified Elements for their team. Editors can skip ahead to [Core Concepts](core-concepts.md).
:::


## What Verified Elements does
- Adds a "Verified until" date field and a "Reviewer" user field to entries and assets, per site.
- Marks each item as **Verified** or **Expired** based on the "Verified until" date.
- Surfaces items that need attention: in a dedicated control panel section, in dashboard widgets, on each user's account page, and by email.
- Lets you verify or assign many items at once with bulk actions.


## Why would I need this?
Let's say your Craft site has "Terms & Conditions" and "Privacy Policy" **entries**, and you want to ensure their content doesn't fall out of date. You simply enable these sections in the plugin's settings, set a "Verified until" date (let's say for 6 months), and assign a Craft user as their "Reviewer". In 6 months, they will be notified that they need to review the content to ensure it's still up to date. This can be helpful for legal experts, translators, and all content editors who need to stay on top of this particular content.

The same can be applied to **assets**. If you have PDFs, for example, that need consistent review, simply set a date and assign a reviewer. 


## Requirements
This plugin requires **Craft CMS 5.6.0** or later, and **PHP 8.2** or later.


## Installation
You can install this plugin from the Plugin Store or with Composer.

### From the Plugin Store
Inside your project’s Control Panel, go to the "Plugin Store," and search for "Verified Elements". Then press "Install".

View in Craft's [online plugin store](https://plugins.craftcms.com/verified-elements).

### With Composer
Open your terminal and run the following commands:

```bash
# 1. Go to your project's directory
cd /path/to/my-project.test

# 2. Tell Composer to load the plugin
composer require webhubworks/craft-verified-elements

# 3. Tell Craft to install the plugin
./craft plugin/install verified-elements
```

## First-time setup checklist
Work through these steps once after installation:

1. **Choose your edition.** The free Lite edition covers entries on a single site, the paid Pro edition adds multi-site support and asset verification. See [Permissions and Editions](permissions-and-editions.md).
2. **Enable the sections you want to track.** Go to **Verified Elements → Settings → Entries** and switch on each section that should be part of the review workflow. See [Settings](settings.md).
3. **Set a default Reviewer and default period per section.** New content in an enabled section automatically receives these defaults. Without a default Reviewer, expired items go unnotified (the settings screen warns you about this).
4. **Enable volumes (Pro only).** Go to **Verified Elements → Settings → Assets** and repeat the same setup for asset volumes. See [Settings](settings.md).
5. **Grant permissions.** In Craft's user group settings, grant "Access Verified Elements", "Verify entries" / "Verify assets", and "Manage Verification Settings" as appropriate. See [Permissions and Editions](permissions-and-editions.md).
6. **Add the dashboard widgets.** Each user can add "Elements to Review" and "Verification Health" to their Craft dashboard. See [Dashboard Widgets](dashboard-widgets.md).
7. **Make sure the expiry check runs.** Reviewer email digests are sent when the plugin's scheduled check runs. Ask your developer to schedule it (see [Email Notifications](email-notifications.md)).


## A typical workflow at a glance
1. An editor saves an entry in an enabled section. The section's default period and default Reviewer are applied automatically.
2. Time passes. The "Verified until" date arrives and the entry becomes **Expired**.
3. The Reviewer receives an email digest, or, perhaps in the CP, they see the entry in their review queue or dashboard widget (there are multiple ways to stay informed on expiring elements).
4. The Reviewer checks the content, updates it if needed, and sets a new "Verified until" date. The entry is **Verified** again.

::: info
The same process can be applied to **Assets**, not just **Entries**.
:::
