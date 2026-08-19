# Querying and Properties

::: info Who's this page for?
Developers working with the code.
:::

The plugin attaches new query params and element properties to `Entry`/`EntryQuery` and
`Asset`/`AssetQuery`, wherever the corresponding edition feature is enabled (`EntryVerification`
or `AssetVerification`).

::: warning
Calling these on an element type, or on an install/edition where the corresponding feature isn't
enabled, throws `UnknownMethodException` because the Yii behavior hasn't been attached in that case.
:::

## Query params
_See below for code examples._

| Param                 | Type | Default | Description |
|-----------------------| --- | --- | --- |
| `isVerified()`        | `bool` | `true` | Whether the results are currently verified or already expired. A `null` "Verified until" date counts as verified indefinitely. |
| `isAssigned()`        | `bool` | `true` | Whether the results have a reviewer assigned. |
| `verifiedUntilDate()` | `mixed` | *(required)* | The "Verified until" date to filter by. Accepts the same formats as [Craft's native date params](https://craftcms.com/docs/5.x/reference/field-types/date-time.html): a `DateTime` object, a date string, an operator-prefixed string (e.g. `'< 2026-08-22'`), an array of these, or `:empty:` / `not :empty:`. |
| `reviewerId()`        | `int`\|`array`\|`null` | `null` | The Craft user ID(s) assigned to verify the elements. |

::: tip
These params always apply a filter when called, including with no arguments (there's no
"unfiltered" call). The **Default** column below is the value used when a param is called with no
argument (e.g. `.reviewerId()` filters for `reviewerId IS NULL`, it doesn't skip filtering).
Leave a param out entirely to not filter by it at all.
:::

## Element properties
_See below for code examples._

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `isVerified` | `bool` (read-only) | `true` | Whether the element is currently verified or already expired. |
| `hasVerifiedUntilDate` | `bool` (read-only) | `false` | Whether a "Verified until" date is set at all. |
| `verifiedUntilDate` | `DateTime`\|`null` | `null` | The "Verified until" date, or `null` for "Indefinitely". |
| `reviewerId` | `int`\|`null` | `null` | The assigned reviewer's user ID. |
| `reviewer` | `User`\|`null` (read-only) | `null` | The assigned reviewer. |
| `verificationStatus` | `VerificationStatus` (read-only) | `Verified` | The `Verified`/`Expired` enum case; call `.label()` for a human-readable string. |


## Examples
The query params and element properties above can be used for either entries or assets. Here are some examples:


### Entries
Let's say you want `Entry` job listings that are assigned to reviewer `1` and expire before August 22, 2026:

::: code-group
```twig [Twig]
{% set jobEntries = craft.entries
    .section('jobListings')
    .verifiedUntilDate('< 2026-08-22')
    .reviewerId(1)
    .all()
%}

{% for entry in jobEntries %}
    <tr>
        <td>{{ entry.title }}</td>
        <td>{{ entry.reviewer ? entry.reviewer.friendlyName : 'Unassigned' }}</td>
        <td>{{ entry.verifiedUntilDate ? entry.verifiedUntilDate|date('Y-m-d') : 'Indefinitely' }}</td>
        <td>{{ entry.verificationStatus.label() }}</td>
    </tr>
{% endfor %}
```

```php [PHP]
use craft\elements\Entry;

$jobEntries = Entry::find()
    ->section('jobListings')
    ->verifiedUntilDate('< 2026-08-22')
    ->reviewerId(1)
    ->all();

foreach ($jobEntries as $entry) {
    $reviewerName = $entry->reviewer?->friendlyName ?? 'Unassigned';
    $verifiedUntil = $entry->verifiedUntilDate?->format('Y-m-d') ?? 'Indefinitely';
    $status = $entry->verificationStatus->label();
    // do something with the data
}
```
:::


### Assets
Let's say you want expired certification `Asset` documents that still need a reviewer assigned:

::: code-group
```twig [Twig]
{% set certificationAssets = craft.assets
    .volume('certifications')
    .isVerified(false)
    .isAssigned(false)
    .all()
%}

{% for asset in certificationAssets %}
    <tr>
        <td>{{ asset.title }}</td>
        <td>{{ asset.reviewer ? asset.reviewer.friendlyName : 'Unassigned' }}</td>
        <td>{{ asset.verifiedUntilDate ? asset.verifiedUntilDate|date('Y-m-d') : 'Indefinitely' }}</td>
        <td>{{ asset.verificationStatus.label() }}</td>
    </tr>
{% endfor %}
```
```php [PHP]
use craft\elements\Asset;

$certificationAssets = Asset::find()
    ->volume('certifications')
    ->isVerified(false)
    ->isAssigned(false)
    ->all();

foreach ($certificationAssets as $asset) {
    $reviewerName = $asset->reviewer?->friendlyName ?? 'Unassigned';
    $verifiedUntil = $asset->verifiedUntilDate?->format('Y-m-d') ?? 'Indefinitely';
    $status = $asset->verificationStatus->label();
    // do something with the data
}
```
:::
