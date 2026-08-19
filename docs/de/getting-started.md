# Erste Schritte

::: info Zielgruppe
Website-Administratoren, die Verified Elements für ihr Team einrichten. Redakteure können direkt zu den [Grundbegriffen](core-concepts.md) springen.
:::


## Was Verified Elements leistet
- Ergänzt Einträge und Dateien um ein "Verifiziert bis"-Datum und einen "Prüfer", pro Website.
- Markiert jedes Element auf Basis dieses Datums als **Verifiziert** oder **Abgelaufen**.
- Macht Elemente sichtbar, die Aufmerksamkeit brauchen: in einem eigenen Control-Panel-Bereich, in Dashboard-Widgets, auf der Kontoseite jedes Benutzers und per E-Mail.
- Erlaubt es, viele Elemente auf einmal zu verifizieren oder zuzuweisen, per Massenaktion.


## Wozu brauche ich das?
Angenommen, Ihre Craft-Website hat **Einträge** wie "AGB" und "Datenschutzerklärung", und Sie möchten sicherstellen, dass deren Inhalt nicht veraltet. Sie aktivieren einfach diese Bereiche in den Einstellungen des Plugins, setzen ein "Verifiziert bis"-Datum (zum Beispiel für 6 Monate) und weisen einen Craft-Benutzer als "Prüfer" zu. Nach 6 Monaten wird dieser benachrichtigt, dass er den Inhalt überprüfen muss, um sicherzustellen, dass er noch aktuell ist. Das kann hilfreich sein für Rechtsabteilungen, Übersetzer und alle Redakteure, die diesen bestimmten Inhalt im Blick behalten müssen.

Dasselbe lässt sich auf **Dateien** anwenden. Wenn Sie zum Beispiel PDFs haben, die regelmäßig überprüft werden müssen, setzen Sie einfach ein Datum und weisen einen Prüfer zu.


## Anforderungen
Dieses Plugin benötigt **Craft CMS 5.6.0** oder neuer sowie **PHP 8.2** oder neuer.


## Installation
Sie können dieses Plugin über den Plugin Store oder mit Composer installieren.

### Über den Plugin Store
Gehen Sie im Control Panel Ihres Projekts zum "Plugin Store" und suchen Sie nach "Verified Elements". Klicken Sie dann auf "Installieren".

Ansehen im [Online-Plugin-Store](https://plugins.craftcms.com/verified-elements) von Craft.

### Mit Composer
Öffnen Sie Ihr Terminal und führen Sie folgende Befehle aus:

```bash
# 1. Wechseln Sie in das Verzeichnis Ihres Projekts
cd /path/to/my-project.test

# 2. Weisen Sie Composer an, das Plugin zu laden
composer require webhubworks/craft-verified-elements

# 3. Weisen Sie Craft an, das Plugin zu installieren
./craft plugin/install verified-elements
```


## Checkliste für die Ersteinrichtung
Arbeiten Sie diese Schritte nach der Installation einmal durch:

1. **Wählen Sie Ihre Edition.** Die kostenlose Lite-Edition deckt Einträge auf einer einzelnen Website ab, die kostenpflichtige Pro-Edition ergänzt Unterstützung für mehrere Websites und die Verifizierung von Dateien. Siehe [Berechtigungen und Editionen](permissions-and-editions.md).
2. **Aktivieren Sie die Bereiche, die Sie verfolgen möchten.** Gehen Sie zu **Verifizierung → Einstellungen → Einträge** und schalten Sie jeden Bereich ein, der Teil des Prüf-Workflows sein soll. Siehe [Einstellungen](settings.md).
3. **Legen Sie pro Bereich einen Standard-Prüfer und eine Standard Gültigkeitsdauer fest.** Neue Inhalte in einem aktivierten Bereich erhalten diese Vorgaben automatisch. Ohne Standard-Prüfer erfolgt für abgelaufene Elemente keine Benachrichtigung (die Einstellungsseite warnt Sie davor).
4. **Aktivieren Sie Volumes (nur Pro).** Gehen Sie zu **Verifizierung → Einstellungen → Dateien** und wiederholen Sie dieselbe Einrichtung für Ihre Volumes. Siehe [Einstellungen](settings.md).
5. **Vergeben Sie Berechtigungen.** Erteilen Sie in Crafts Benutzergruppen-Einstellungen die Berechtigungen "Zugriff auf Verifizierung", "Einträge verifizieren" bzw. "Dateien verifizieren" und "Verifizierungseinstellungen verwalten", je nach Rolle. Siehe [Berechtigungen und Editionen](permissions-and-editions.md).
6. **Fügen Sie die Dashboard-Widgets hinzu.** Jeder Benutzer kann "Elemente zum Prüfen" und "Verification Health" zu seinem Craft-Dashboard hinzufügen. Siehe [Dashboard-Widgets](dashboard-widgets.md).
7. **Stellen Sie sicher, dass die Ablaufprüfung läuft.** E-Mail-Zusammenfassungen an Prüfer werden versendet, wenn die geplante Prüfung des Plugins läuft. Bitten Sie Ihren Entwickler, sie einzuplanen (siehe [E-Mail-Benachrichtigungen](email-notifications.md)).


## Ein typischer Workflow im Überblick
1. Ein Redakteur speichert einen Eintrag in einem aktivierten Bereich. Die Standard Gültigkeitsdauer und der Standard-Prüfer des Bereichs werden automatisch angewendet.
2. Die Zeit vergeht. Das "Verifiziert bis"-Datum ist erreicht und der Eintrag wird **Abgelaufen**.
3. Der Prüfer erhält eine E-Mail-Zusammenfassung, oder sieht den Eintrag vielleicht im CP in seiner Prüfliste oder im Dashboard-Widget (es gibt mehrere Wege, über ablaufende Elemente informiert zu bleiben).
4. Der Prüfer kontrolliert den Inhalt, aktualisiert ihn bei Bedarf und setzt ein neues "Verifiziert bis"-Datum. Der Eintrag ist wieder **Verifiziert**.

::: info
Derselbe Ablauf lässt sich auch auf **Dateien** anwenden, nicht nur auf **Einträge**.
:::
