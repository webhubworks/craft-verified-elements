# Massenaktionen

::: info Zielgruppe
Benutzer mit der Berechtigung "Einträge verifizieren" oder "Dateien verifizieren".
:::

Verified Elements ergänzt jede Eintrags- und Dateiliste im Control Panel um zwei Aktionen. Sie funktionieren im eigenen [Plugin-CP-Bereich](plugin-cp-section.md) des Plugins ebenso wie in Crafts regulären Bereichen **Einträge** und **Dateien**.

## Verifizieren

Setzt in einem Schritt ein neues "Verifiziert bis"-Datum auf alle ausgewählten Elemente.

1. Wählen Sie ein oder mehrere Elemente in der Liste aus (Checkboxen).
2. Öffnen Sie das Aktionsmenü und wählen Sie **Verifizieren**.
3. Wählen Sie im Dialog unter "Verifizieren für" eine Gültigkeitsdauer: 7 Tage, 30 Tage, 90 Tage, 1 Jahr, Unbegrenzt oder Bestimmtes Datum (blendet eine Datumsauswahl ein).
4. Bestätigen Sie. Alle ausgewählten Elemente erhalten das neue Datum und gelten wieder als Verifiziert.

![Bulk action: verifiy](/screenshots/bulk-actions/verify.png)

::: tip
Nutzen Sie das nach einer Prüfrunde: Filtern Sie die Liste der abgelaufenen Elemente, kontrollieren Sie die Inhalte, wählen Sie alles aus, was noch korrekt ist, und verifizieren Sie es in einem Zug.
:::


## Prüfer zuweisen

Weist einen Craft-Benutzer als Prüfer allen ausgewählten Elementen zu.

1. Wählen Sie ein oder mehrere Elemente in der Liste aus.
2. Öffnen Sie das Aktionsmenü und wählen Sie **Prüfer zuweisen**.
3. Wählen Sie im Auswahldialog einen Benutzer. Angeboten werden nur aktive Benutzer, die diesen Element-Typ verifizieren dürfen.
4. Bestätigen Sie. Der Benutzer ist jetzt für alle ausgewählten Elemente verantwortlich.

![Bulk action: assign reviewer](/screenshots/bulk-actions/assign-reviewer.png)

::: tip
Nutzen Sie das zusammen mit dem Filter **Unbestimmt** im Bereich des Plugins, um sicherzustellen, dass jedes ablaufende Element jemanden hat, der es im Blick behält.
:::


## Hinweise

- Beide Aktionen speichern jedes ausgewählte Element, es gilt also das übliche Speicherverhalten (einschließlich Änderungsbenachrichtigungen an Prüfer, siehe [E-Mail-Benachrichtigungen](email-notifications.md)).
- Konnten einige Elemente nicht gespeichert werden, meldet die Aktion, dass nicht alle Elemente verarbeitet wurden. Führen Sie sie für die verbliebene Auswahl erneut aus.
