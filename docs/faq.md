# FAQ and Troubleshooting


## I don't see the Verification panel on an entry
Check, in this order:

1. Is the entry's **section enabled** in **Verified Elements → Settings → Entries** (on the site you are editing)?
2. Do you have the **"Verify entries"** permission? Without it the panel is hidden (the status still shows in the metadata).
3. On a multi-site installation with the Lite edition, only the **primary site** is tracked.
4. Nested entries inside Matrix fields aren't verifiable, only regular section entries.


## I don't see the plugin's CP section in the navigation
You need the **"Access Verified Elements"** permission. Ask an administrator.


## Why did a new entry get a Reviewer and date I never set?
The section has a **default period** and **default Reviewer** configured. They're applied automatically on first save. You can override both in the Verification panel at any time.


## Nobody received an email although content expired
1. Does the expired element have a **Reviewer**? Unassigned elements are reported to the site's system email address only. Check the **Unassigned** filter.
2. Has the **expiry check** run since the element expired? Digests are sent when the scheduled check or Craft's routine maintenance runs. Ask your developer whether `php craft verified-elements/check-expired-verifications` is scheduled.
3. Can your site send email at all? Test under **Settings → Email** in Craft.


## I keep receiving the same digest email
Digests repeat while assigned elements stay expired. Review the items and set a new "Verified until" date to stop the reminders. For content that never goes stale, choose **Indefinitely**.


## What does "Indefinite" in the "Verified until" column mean?
No expiry date is set. The element counts as Verified and will never expire. Elements in enabled sections that were never explicitly verified also show as Indefinite, and there's currently no way to tell the two apart — a section's default period can itself be configured as "Indefinitely", in which case first save behaves the same way as someone choosing it by hand.


## Can two people review the same element?
Each element (per site) has exactly one Reviewer. For a shared responsibility, create a Craft user with a team mailbox (for example "content-team@...") and assign that.


## Does verifying an entry publish or change it?
No. Verification data lives alongside your content. Setting a date or Reviewer does save the element, but doesn't alter its fields, status, or publish dates.


## We added a new site. Why is nothing tracked there?
Sections must be enabled per site: open **Verified Elements → Settings → Entries**, switch to the new site's tab, and enable them. Asset volume settings carry over to new sites automatically. Multi-site tracking requires the Pro edition.


## Who can I contact for help?
- Bug reports: [Post an issue](https://github.com/webhubworks/craft-verified-elements/issues) on GitHub.
- User support: <support@webhub.de>.
