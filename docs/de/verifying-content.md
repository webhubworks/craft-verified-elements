# Inhalte verifizieren

::: info Zielgruppe
Alle, die Einträge oder Dateien bearbeiten. Um die Verifizierungsfelder zu bearbeiten, benötigen Sie die Berechtigung "Einträge verifizieren" (bzw. "Dateien verifizieren").
:::

## Das Verifizierungs-Panel auf der Bearbeitungsseite

Wenn Sie einen Eintrag in einem aktivierten Bereich (oder eine Datei in einem aktivierten Volume) bearbeiten, erscheint in der rechten Seitenleiste ein Panel **Verifizierung**. Es enthält zwei Felder:

### Verifiziert bis

Ein Auswahlfeld mit folgenden Optionen:

- **7 Tage, 30 Tage, 90 Tage, 1 Jahr**. Setzt das Ablaufdatum entsprechend weit ab heute.
- **Bestimmtes Datum**. Öffnet eine Datumsauswahl, mit der Sie einen exakten Tag wählen können.
- **Unbegrenzt**. Kein Ablauf. Das Element bleibt Verifiziert, bis jemand etwas anderes entscheidet.

Ist das Element aktuell abgelaufen, wird das Feld mit einem Warnsymbol markiert. Ist es verifiziert, zeigt die Panel-Überschrift ein Häkchen-Symbol.

### Prüfer

Wählen Sie den Craft-Benutzer, der für die Überprüfung dieses Elements verantwortlich ist, wenn es abläuft. Zur Auswahl stehen nur aktive Benutzer, die diesen Element-Typ verifizieren dürfen.

<div class="screenshot-row">
    <img src="/screenshots/verifying-content/reviewer-select-verified.png" alt="Reviewer select: verified" />
    <img src="/screenshots/verifying-content/reviewer-select-expired.png" alt="Reviewer select: expired" />
</div>

::: tip
Legen Sie in Crafts [Benutzerberechtigungen](https://craftcms.com/docs/5.x/system/user-management.html#permissions) fest, wer Inhalte prüfen oder die Verifizierungsfelder eines Eintrags bzw. einer Datei bearbeiten darf.
:::

## Die Statusanzeige

Neben den Feldern zeigt der Metadaten-Bereich der Bearbeitungsseite (unter "Status") den aktuellen Verifizierungsstatus, **Verifiziert** oder **Abgelaufen**, mit einem farbigen Indikator.

## Was beim Speichern passiert

- Beim Speichern des Elements werden Verifizierungsdatum und Prüfer **für die Website gespeichert, die Sie gerade bearbeiten**. Auf Installationen mit mehreren Websites behalten die anderen Websites ihren eigenen Verifizierungsstatus.
- Ist das Element **neu** und haben Sie selbst kein Datum gesetzt, wird automatisch die **Standard Gültigkeitsdauer** des Bereichs oder Volumes angewendet. Ein **Standard-Prüfer** wird ebenfalls gesetzt, sofern der Bereich einen hat.
- Ist ein Prüfer zugewiesen und das Element aktuell verifiziert, löst das Speichern einer Änderung eine kurze E-Mail an den Prüfer aus ("Ein Eintrag wurde aktualisiert" bzw. "Eine Datei wurde aktualisiert"), damit er die Änderungen nachprüfen kann. Siehe [E-Mail-Benachrichtigungen](email-notifications.md).

## Direkt aus einer Liste verifizieren

Sie müssen nicht jedes Element öffnen. In jeder Eintrags- oder Dateiliste können Sie:

- Die Spalten des Plugins (**Verifizierung**, **Verifiziert bis**, **Prüfer**) über die Spalteneinstellungen der Liste einblenden.
- Nach "Verifiziert bis" sortieren.
- Das "Verifiziert bis"-Datum und den Prüfer direkt in der Tabelle bearbeiten (bei aktivierter Inline-Bearbeitung).
- Mehrere Elemente auswählen und die Massenaktionen **Verifizieren** oder **Prüfer zuweisen** nutzen. Siehe [Massenaktionen](bulk-actions.md).

<img src="/screenshots/verifying-content/entries-index-with-ver-columns.png" alt="Entries index with verification columns" />

## Listen nach Verifizierungsstatus filtern

Das Plugin ergänzt Crafts Element-Filter um drei Regeln, mit denen Sie eigene gefilterte Ansichten auf jeder Eintrags- oder Dateiliste bauen können:

- **Verifiziert** (ja oder nein)
- **Verifiziert-bis-Datum** (Datumsvergleich)
- **Prüfer** (bestimmter Benutzer)

<img src="/screenshots/verifying-content/filter-lists-by-ver-state.png" alt="Filtering lists by verification state" />
