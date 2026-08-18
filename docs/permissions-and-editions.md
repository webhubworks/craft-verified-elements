# Permissions and Editions

::: info Audience
Administrators.
:::

## Permissions

Verified Elements plugs into Craft's normal user and permission system. Assign these in **Settings → Users → User Groups** (or per user):

| Permission | What it grants                                                                                                                                          |
|---|---------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Access Verified Elements** (under "Access the control panel") | See the plugin's CP section in the navigation, use its listings, add its dashboard widgets, and get the "Verified Elements" page on the account screen. |
| **Verify entries** | Edit the Verification fields on entries (edit page and inline in lists) and use the Verify and Assign Reviewer bulk actions on entry listings.          |
| **Verify assets** (Pro) | The same, for assets.                                                                                                                                   |
| **Manage Verification Settings** | See and use the plugin's Settings pages.                                                                                                                |

Notes:

- Admin users implicitly have every permission.
- **Who can be picked as a Reviewer:** the Reviewer fields and the Assign Reviewer action offer active users who hold the matching verify permission.
- **Being a Reviewer isn't a permission.** Assignments stay in place even if a user's permissions change later. A user who can access the plugin but not verify can still be assigned and will still see their queue and receive emails.

### Suggested setups

- **Editors who maintain their own content:** Access Verified Elements + Verify entries.
- **Reviewers from outside the editorial team** (for example legal or product experts): Access Verified Elements only; they review, editors verify.
- **Content leads / admins:** all four permissions.

TODO: Screenshot of the plugin's permission group in Craft's user group settings.

## Editions

The plugin comes in two editions: the free **Lite** edition and the paid **Pro** edition. Both include the full review workflow; Pro extends it to every site and to assets.

| Feature | Lite | Pro |
|---|:---:|:---:|
| Entry verification | ✓ | ✓ |
| Reviewer assignment | ✓ | ✓ |
| Verification periods per section | ✓ | ✓ |
| Expiry email notifications | ✓ | ✓ |
| Dashboard widgets | ✓ | ✓ |
| Bulk verification actions | ✓ | ✓ |
| Multi-site support | | ✓ |
| Asset verification | | ✓ |

What this means in the interface:

- **Lite:** entries only, and verification applies to the primary site only. On other sites, the Verification panel and columns don't appear.
- **Pro:** every site is tracked, and assets join the workflow. Settings gain per-site tabs and an Assets page, listings gain a site switcher, the plugin's CP section gains an Assets page, the Verification Health widget gains a site setting, and the "Verify assets" permission becomes available.

You can switch editions at any time from the plugin's page on the Craft Plugin Store, or via **Verified Elements → Settings → Subscription Plan**.

TODO: Confirm pricing and store links once the plugin is published.
