# Der Plugin-CP-Bereich

::: info Zielgruppe
Alle, die mit der Verifizierung arbeiten. Der Bereich erscheint in der Control-Panel-Navigation für Benutzer mit der Berechtigung "Zugriff auf Verifizierung".
:::

Das Plugin fügt der Hauptnavigation des Control Panels einen eigenen Bereich **Verifizierung** hinzu. Das ist die Kommandozentrale des Prüf-Workflows: ein Ort, an dem Sie jeden verfolgten Eintrag und jede verfolgte Datei sehen, gefiltert nach Verifizierungsstatus oder Prüfer.

## Seiten
Das Plugin fügt Crafts linker Hauptnavigation einen eigenen Bereich namens "Verifizierung" hinzu. Je nach Edition können folgende Unterseiten enthalten sein:

- **Einträge**. Alle Einträge in aktivierten Bereichen. Das ist die Startseite des Bereichs.
- **Dateien** (Pro). Alle Dateien in aktivierten Volumes.
- **Einstellungen** (sichtbar mit der Berechtigung "Verifizierungseinstellungen verwalten"). Siehe [Einstellungen](settings.md).

![CP Section](/screenshots/plugin-cp-section/landing.png)

## Filter in der Seitenleiste

Die linke Seitenleiste gruppiert Elemente nach Status (siehe Sub-Navigation im Screenshot oben):

- **Abgelaufen**. Elemente, deren "Verifiziert bis"-Datum überschritten ist. Diese brauchen jetzt Aufmerksamkeit.
- **Bevorstehend**. Verifizierte Elemente, die innerhalb der nächsten 30 Tage ablaufen. Handeln Sie hier, um vorne zu bleiben.
- **Verifiziert**. Alles, was aktuell in Ordnung ist (einschließlich unbegrenzt verifizierter Elemente).
- **Unbestimmt**. Elemente ohne Prüfer. Eine Kennzahl zeigt, wie viele davon ein Ablaufdatum tragen und daher ablaufen werden, ohne dass jemand benachrichtigt wird. Weisen Sie diesen zuerst Prüfer zu.

Darunter listet eine Gruppe **Prüfer** einen Filter pro Person:

- **Ihr eigener Name** steht immer an erster Stelle und zeigt die Ihnen zugewiesenen Elemente.
- Jeder andere Benutzer, der aktuell Prüfaufträge hat, erhält einen eigenen Filter.

::: note
Siehe die Seite [Grundbegriffe](core-concepts.md) für Erklärungen zu diesen Begriffen.
:::

## Die Tabelle

Jede Zeile zeigt das Element mit seinen Verifizierungsdaten:

- **Verifizierung**: der Status (Verifiziert oder Abgelaufen)
- **Verifiziert bis**, in Klartext dargestellt ("Heute", "Noch 5 Tage", "Vor 12 Tagen" oder "Unbegrenzt")
- **Prüfer**, oder kursiv "Unbestimmt"

Sie können nach "Verifiziert bis" sortieren, suchen, die Website wechseln (Pro-Edition) und Spalten anpassen wie in jeder Craft-Liste. Das "Verifiziert bis"-Datum und der Prüfer lassen sich direkt in der Tabelle bearbeiten, ohne das Element zu öffnen.

## Viele Elemente auf einmal bearbeiten

Wählen Sie beliebig viele Zeilen aus und nutzen Sie die Aktion **Verifizieren** oder **Prüfer zuweisen** am unteren Rand der Liste. Siehe [Massenaktionen](bulk-actions.md).

![Acting on many elements at once](/screenshots/plugin-cp-section/bulk-actions.png)
