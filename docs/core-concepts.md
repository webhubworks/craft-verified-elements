# Core Concepts
A short glossary of the terms used throughout the plugin and this manual.


## Element
Craft's word for a piece of content. Verified Elements currently works with two element types:

- **Entries**, available in every edition.
- **Assets** (files in your asset volumes), available in the **Pro** edition.

::: note
Verification only applies to elements in sections and volumes that an administrator has enabled in the plugin's settings.
:::


## "Verified until" date
The date up to which a piece of content is considered accurate. You set it by choosing a period (7 days, 30 days, 90 days, 1 year) or picking a specific date. By default, the date is set to **Indefinitely**.


## Verification status
Every tracked element is in one of two states:

- **Verified** (green). The "Verified until" date is in the future, or the element is verified indefinitely.
- **Expired** (red). The "Verified until" date has passed. The element needs review.

::: note
The status appears only in the CP: on an entry or asset's edit pages, in the columns of an element listing page, in the plugin's own section (found in Craft's main nav), and in dashboard widgets.
:::


## Indefinite ("Verified until" date)
An element verified "Indefinitely" has no expiry date. It counts as Verified forever, never appears in review queues, and never triggers expiry emails. Use it for content that doesn't go stale, such as legal pages that are reviewed through another process.


## Imminent ("Verified until" date)
An element whose "Verified until" date lies within the next 30 days. The plugin's CP section has an "Imminent" filter so you can act before content actually expires.


## Reviewer
The Craft user responsible for re-checking an element when it expires. A Reviewer:

- receives an email digest when their assigned elements expire,
- receives an alert email when an assigned, verified element is changed,
- sees their assignments in the "Elements to Review" widget and on their account page.

::: warning
Elements without a Reviewer are **Unassigned**. Expired unassigned elements are reported to the site's system email address instead of a person.
:::


## Default Reviewer and default period
Per **entry** section (and per **asset** volume), administrators can define a default Reviewer and a default verification period. New content picks these up automatically on first save, so editors don't have to remember to set them.


## Site scope
On multi-site installations with the Pro edition, verification is tracked **per site**: the same entry can be Verified on one site and Expired on another. Without multi-site support (Lite edition), the plugin manages the primary site only.
