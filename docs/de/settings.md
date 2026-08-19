# Einstellungen

::: info An wen richtet sich diese Seite?
Administratoren und Benutzer mit der Berechtigung "Verifizierungseinstellungen verwalten". Die Einstellungen finden Sie im Control Panel unter **Verifizierung → Einstellungen**.
:::

Die Einstellungen steuern, **welche Inhalte verfolgt werden** und **welche Vorgaben** neue Inhalte erhalten.

## Einträge

Eine Tabelle aller Bereiche Ihrer Installation. Pro Bereich konfigurieren Sie:

- **Aktiviert**. Schaltet die Verifizierung für diesen Bereich ein oder aus. Nur aktivierte Bereiche zeigen das Verifizierungs-Panel, erscheinen in den Listen des Plugins und versenden Benachrichtigungen.
- **Standard-Prüfer**. Der Benutzer, der neuen Einträgen in diesem Bereich automatisch zugewiesen wird (wenn der Eintrag ein Verifizierungsdatum erhält und noch keinen Prüfer hat). Ist ein Bereich ohne Standard-Prüfer aktiviert, warnt die Seite: Abgelaufene Einträge ohne Prüfer werden nur an die System-E-Mail-Adresse gemeldet, nicht an eine Person.
- **Standard Gültigkeitsdauer**. Die "Verifiziert bis"-Dauer, die beim ersten Speichern eines Eintrags automatisch angewendet wird: 7 Tage, 30 Tage, 90 Tage, 1 Jahr oder Unbegrenzt. Redakteure können sie pro Eintrag jederzeit überschreiben.

**Mehrere Websites (Pro):** Die Seite hat einen Tab pro Website. Jede Website hat ihre eigene Aktivierung und ihre eigenen Vorgaben, sodass ein Bereich auf einer Website mit anderen Prüfern verfolgt werden kann als auf einer anderen. Denken Sie daran, den Tab jeder Website separat zu speichern.

![Entry section settings](/screenshots/settings/entries.png)

## Dateien (Pro)

Dieselbe Tabelle für Volumes: Aktiviert, Standard-Prüfer, Standard Gültigkeitsdauer pro Volume. Volumes sind in Craft nicht Website-spezifisch, diese Einstellungen gelten also für alle Websites zugleich, es gibt keine Website-Tabs.

![Asset section settings](/screenshots/settings/assets.png)


## Gut zu wissen

- Vorgaben gelten nur für **neue** Inhalte. Das Ändern der Standard Gültigkeitsdauer eines Bereichs schreibt keine Daten bestehender Einträge um; nutzen Sie dafür die Massenaktion **Verifizieren**.
- Das Deaktivieren eines Bereichs oder Volumes blendet dessen Verifizierungs-Oberfläche aus und stoppt seine Benachrichtigungen, rührt aber die gespeicherten Daten nicht an: Bestehende "Verifiziert bis"-Daten und Prüfer-Zuweisungen (sowohl pro Element als auch die Standardwerte des Bereichs) bleiben in der Datenbank vollständig unverändert. Solange deaktiviert, wird die Verifizierung beim Speichern seiner Elemente komplett übersprungen (kein Abgleich von Datum/Prüfer, keine Änderungshinweise), und seine abgelaufenen Elemente werden von Zusammenfassungs-E-Mails ausgeschlossen. Beim erneuten Aktivieren wird alles genau wie zuvor wiederhergestellt; einzige Besonderheit: Elemente, die während der Deaktivierung abgelaufen sind, erscheinen in der nächsten Zusammenfassung, da sie nie als gemeldet gezählt wurden.
- Wird eine neue Website angelegt, werden die Volume-Einstellungen automatisch auf sie übertragen. Eintrags-Bereiche starten auf der neuen Website unkonfiguriert und müssen auf ihrem Website-Tab aktiviert werden.
