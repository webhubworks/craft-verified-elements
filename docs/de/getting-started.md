# Erste Schritte

::: info Zielgruppe
Website-Administratoren, die Verified Elements für ihr Team einrichten. Redakteure können direkt zu den [Grundbegriffen](core-concepts.md) springen.
:::

## Was Verified Elements leistet

- Ergänzt Einträge und Dateien um ein "Verifiziert bis"-Datum und einen "Prüfer", pro Website.
- Markiert jedes Element auf Basis dieses Datums als **Verifiziert** oder **Abgelaufen**.
- Macht Elemente sichtbar, die Aufmerksamkeit brauchen: in einem eigenen Control-Panel-Bereich, in Dashboard-Widgets, auf der Kontoseite jedes Benutzers und per E-Mail.
- Erlaubt es, viele Elemente auf einmal zu verifizieren oder zuzuweisen, per Massenaktion.

## Voraussetzungen und Installation

Das Plugin setzt Craft CMS 5.6 oder neuer voraus. Die Installation erfolgt über den Craft Plugin Store oder Composer und wird üblicherweise von Ihrem Entwickler durchgeführt. Die genauen Schritte finden Sie in der [README des Repositories](https://github.com/webhubworks/craft-verified-elements#installation).

Plugin Store: [Craft Verified Elements](https://plugins.craftcms.com/verified-elements).

## Checkliste für die Ersteinrichtung

Arbeiten Sie diese Schritte nach der Installation einmal durch:

1. **Wählen Sie Ihre Edition.** Die kostenlose Standard-Edition deckt Einträge auf einer einzelnen Website ab, die kostenpflichtige Pro-Edition ergänzt Unterstützung für mehrere Websites und die Verifizierung von Dateien. Siehe [Berechtigungen und Editionen](permissions-and-editions.md).
2. **Aktivieren Sie die Bereiche, die Sie verfolgen möchten.** Gehen Sie zu **Verifizierung → Einstellungen → Einträge** und schalten Sie jeden Bereich ein, der Teil des Prüf-Workflows sein soll. Siehe [Einstellungen](settings.md).
3. **Legen Sie pro Bereich einen Standard-Prüfer und eine Standard Gültigkeitsdauer fest.** Neue Inhalte in einem aktivierten Bereich erhalten diese Vorgaben automatisch. Ohne Standard-Prüfer erfolgt für abgelaufene Elemente keine Benachrichtigung (die Einstellungsseite warnt Sie davor).
4. **Aktivieren Sie Volumes (nur Pro).** Gehen Sie zu **Verifizierung → Einstellungen → Dateien** und wiederholen Sie dieselbe Einrichtung für Ihre Volumes.
5. **Vergeben Sie Berechtigungen.** Erteilen Sie in Crafts Benutzergruppen-Einstellungen die Berechtigungen "Zugriff auf Verifizierung", "Einträge verifizieren" bzw. "Dateien verifizieren" und "Verifizierungseinstellungen verwalten", je nach Rolle. Siehe [Berechtigungen und Editionen](permissions-and-editions.md).
6. **Fügen Sie die Dashboard-Widgets hinzu.** Jeder Benutzer kann "Elemente zum Prüfen" und "Verification Health" zu seinem Craft-Dashboard hinzufügen. Siehe [Dashboard-Widgets](dashboard-widgets.md).
7. **Stellen Sie sicher, dass die Ablaufprüfung läuft.** E-Mail-Zusammenfassungen an Prüfer werden versendet, wenn die geplante Prüfung des Plugins läuft. Bitten Sie Ihren Entwickler, sie einzuplanen (siehe [E-Mail-Benachrichtigungen](email-notifications.md)).

## Ein typischer Workflow im Überblick

1. Ein Redakteur speichert einen Eintrag in einem aktivierten Bereich. Die Standard Gültigkeitsdauer und der Standard-Prüfer des Bereichs werden automatisch angewendet.
2. Die Zeit vergeht. Das "Verifiziert bis"-Datum ist erreicht und der Eintrag wird **Abgelaufen**.
3. Der Prüfer erhält eine E-Mail-Zusammenfassung und sieht den Eintrag in seiner Prüfliste und im Dashboard-Widget.
4. Der Prüfer kontrolliert den Inhalt, aktualisiert ihn bei Bedarf und setzt ein neues "Verifiziert bis"-Datum. Der Eintrag ist wieder **Verifiziert**.
