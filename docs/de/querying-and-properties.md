# Abfragen und Eigenschaften

::: info Zielgruppe
Entwickler, die mit dem Code arbeiten.
:::

Das Plugin fügt `Entry`/`EntryQuery` und `Asset`/`AssetQuery` neue Abfrageparameter und
Element-Eigenschaften hinzu, sofern die entsprechende Edition-Funktion aktiviert ist
(`EntryVerification` oder `AssetVerification`).

::: warning
Der Aufruf auf einem Element-Typ oder einer Installation/Edition, in der die entsprechende
Funktion nicht aktiviert ist, wirft eine `UnknownMethodException`, da das Yii-Behavior in diesem
Fall nicht angehängt wurde.
:::

## Abfrageparameter
_Siehe die Codebeispiele weiter unten._

| Parameter             | Typ | Standard | Beschreibung |
|-----------------------| --- | --- | --- |
| `isVerified()`        | `bool` | `true` | Ob die Ergebnisse aktuell verifiziert oder bereits abgelaufen sind. Ein `null`-Wert im Feld "Verifiziert bis" gilt als unbegrenzt verifiziert. |
| `isAssigned()`        | `bool` | `true` | Ob den Ergebnissen ein Prüfer zugewiesen ist. |
| `verifiedUntilDate()` | `mixed` | *(erforderlich)* | Das Datum "Verifiziert bis", nach dem gefiltert werden soll. Akzeptiert dieselben Formate wie [Crafts native Datumsparameter](https://craftcms.com/docs/5.x/reference/field-types/date-time.html): ein `DateTime`-Objekt, einen Datums-String, einen String mit vorangestelltem Operator (z. B. `'< 2026-08-22'`), ein Array davon, oder `:empty:` / `not :empty:`. |
| `reviewerId()`        | `int`\|`array`\|`null` | `null` | Die Craft-Benutzer-ID(s), die für die Verifizierung der Elemente zuständig sind. |

::: tip
Diese Parameter wenden beim Aufruf immer einen Filter an, auch ohne Argumente (es gibt keinen
"ungefilterten" Aufruf). Die Spalte **Standard** unten zeigt den Wert, der verwendet wird, wenn
ein Parameter ohne Argument aufgerufen wird (z. B. filtert `.reviewerId()` nach
`reviewerId IS NULL`, statt die Filterung zu überspringen). Lassen Sie einen Parameter ganz weg,
um überhaupt nicht danach zu filtern.
:::

## Element-Eigenschaften
_Siehe die Codebeispiele weiter unten._

| Eigenschaft | Typ | Standard | Beschreibung |
| --- | --- | --- | --- |
| `isVerified` | `bool` (nur lesbar) | `true` | Ob das Element aktuell verifiziert oder bereits abgelaufen ist. |
| `hasVerifiedUntilDate` | `bool` (nur lesbar) | `false` | Ob überhaupt ein "Verifiziert bis"-Datum gesetzt ist. |
| `verifiedUntilDate` | `DateTime`\|`null` | `null` | Das Datum "Verifiziert bis", oder `null` für "Unbegrenzt". |
| `reviewerId` | `int`\|`null` | `null` | Die Benutzer-ID des zugewiesenen Prüfers. |
| `reviewer` | `User`\|`null` (nur lesbar) | `null` | Der zugewiesene Prüfer. |
| `verificationStatus` | `VerificationStatus` (nur lesbar) | `Verified` | Der Enum-Wert `Verified`/`Expired`; rufen Sie `.label()` für eine lesbare Zeichenkette auf. |


## Beispiele
Die oben beschriebenen Abfrageparameter und Element-Eigenschaften lassen sich sowohl für Einträge
als auch für Dateien verwenden. Hier einige Beispiele:


### Einträge
Angenommen, Sie möchten `Entry`-Stellenanzeigen, die Prüfer `1` zugewiesen sind und vor dem
22. August 2026 ablaufen:

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


### Dateien
Angenommen, Sie möchten abgelaufene Zertifizierungs-`Asset`-Dokumente, denen noch kein Prüfer
zugewiesen ist:

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
