# Berechtigungen und Editionen

::: info An wen richtet sich diese Seite?
Administratoren.
:::


## Berechtigungen
Verified Elements fügt sich in Crafts normales Benutzer- und Berechtigungssystem ein. Vergeben Sie diese Berechtigungen unter **Einstellungen → Benutzer → Benutzergruppen** (oder pro Benutzer):

| Berechtigung | Was sie erlaubt |
|---|---|
| **Zugriff auf Verifizierung** ("Access Verified Elements", unter "Zugriff auf das Control Panel") | Den Bereich des Plugins in der Navigation sehen, seine Listen nutzen, seine Dashboard-Widgets hinzufügen und die Seite "Verifizierung" auf der Kontoseite erhalten. |
| **Einträge verifizieren** | Die Verifizierungsfelder an Einträgen bearbeiten (Bearbeitungsseite und direkt in Listen) sowie die Massenaktionen Verifizieren und Prüfer zuweisen auf Eintragslisten nutzen. |
| **Dateien verifizieren** (Pro) | Dasselbe, für Dateien. |
| **Verifizierungseinstellungen verwalten** | Die Einstellungsseiten des Plugins sehen und nutzen. |

::: note
- Admin-Benutzer haben implizit jede Berechtigung.
- **Wer als Prüfer gewählt werden kann:** Die Prüfer-Felder und die Aktion "Prüfer zuweisen" bieten aktive Benutzer an, die die passende Verifizierungs-Berechtigung besitzen.
- **Prüfer zu sein ist keine Berechtigung.** Zuweisungen bleiben bestehen, auch wenn sich die Berechtigungen eines Benutzers später ändern. Ein Benutzer, der auf das Plugin zugreifen, aber nicht verifizieren darf, kann trotzdem zugewiesen werden, sieht weiterhin seine Prüfliste und erhält E-Mails.
:::

### Empfohlene Konfigurationen
- **Redakteure, die ihre eigenen Inhalte pflegen:** Zugriff auf Verifizierung + Einträge verifizieren.
- **Prüfer außerhalb der Redaktion** (zum Beispiel Rechtsabteilung oder Produktexperten): nur Zugriff auf Verifizierung; sie prüfen, Redakteure verifizieren.
- **Content-Leads / Admins:** alle vier Berechtigungen.


## Editionen
Das Plugin gibt es in zwei Editionen: der kostenlosen **Lite**-Edition und der kostenpflichtigen **Pro**-Edition. Beide enthalten den vollständigen Prüf-Workflow; Pro erweitert ihn auf alle Websites und auf Dateien.

| Funktion | Lite | Pro |
|---|:---:|:---:|
| Verifizierung von Einträgen | ✓ | ✓ |
| Zuweisung von Prüfern | ✓ | ✓ |
| Gültigkeitsdauer pro Bereich | ✓ | ✓ |
| E-Mail-Benachrichtigungen bei Ablauf | ✓ | ✓ |
| Dashboard-Widgets | ✓ | ✓ |
| Massenaktionen zur Verifizierung | ✓ | ✓ |
| Unterstützung für mehrere Websites | | ✓ |
| Verifizierung von Dateien | | ✓ |

Was das in der Oberfläche bedeutet:

- **Lite:** nur Einträge, und die Verifizierung gilt nur für die primäre Website. Auf anderen Websites erscheinen das Verifizierungs-Panel und die Spalten nicht.
- **Pro:** Jede Website wird verfolgt und Dateien kommen zum Workflow hinzu. Die Einstellungen erhalten Tabs pro Website und eine Dateien-Seite, Listen erhalten einen Website-Umschalter, der Bereich des Plugins erhält eine Dateien-Seite, das Widget "Verification Health" erhält eine Website-Einstellung, und die Berechtigung "Dateien verifizieren" wird verfügbar.

::: tip
Sie können die Edition jederzeit über die Seite des Plugins im Craft Plugin Store wechseln, oder über **Verifizierung → Einstellungen → Abonnement**.
:::
