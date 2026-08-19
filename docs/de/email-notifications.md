# E-Mail-Benachrichtigungen

Das Plugin versendet zwei Arten von E-Mails. Beide verwenden die normalen E-Mail-Einstellungen Ihrer Website, sie kommen also von der Adresse, mit der Ihre Craft-Installation konfiguriert ist.

## Ablauf-Zusammenfassungen

Wenn Elemente ablaufen, werden ihre Prüfer benachrichtigt:

- **Pro Prüfer:** Jeder Prüfer erhält eine Zusammenfassungs-E-Mail mit allen seinen abgelaufenen Elementen ("3 Elemente benötigen Ihre Verifizierung"), mit Links zur Überprüfung jedes Elements.
- **Nicht zugewiesene Elemente:** Abgelaufene Elemente ohne Prüfer werden in einer Zusammenfassung an die **System-E-Mail-Adresse** der Website gesammelt, damit sie nicht unbemerkt durchrutschen. Halten Sie die Liste der nicht zugewiesenen Elemente kurz, indem Sie Prüfer zuweisen (siehe [Massenaktionen](bulk-actions.md)).
- **Inaktive Prüfer:** Existiert das Konto eines Prüfers nicht mehr oder ist es inaktiv, wandern seine abgelaufenen Elemente in den Bericht für nicht zugewiesene Elemente.

![Expiry digest email](/screenshots/email-notifications/expiry-digest.png)

### Wann Zusammenfassungen versendet werden

Zusammenfassungen werden versendet, wenn die Ablaufprüfung des Plugins läuft:

- Während Crafts routinemäßiger Wartung (Garbage Collection), die regelmäßig von selbst läuft.
- Wenn der geplante Prüfbefehl ausgeführt wird. Website-Administratoren sollten ihren Entwickler bitten, `php craft verified-elements/check-expired-verifications` **einmal täglich** einzuplanen (zum Beispiel nächtlich per Cron), damit Zusammenfassungen verlässlich ankommen.

Ein Element erscheint so lange in den Zusammenfassungen, bis es wieder verifiziert ist, damit nichts vergessen wird. Eine lange ignorierte Liste bedeutet aber auch wiederholte E-Mails. Arbeiten Sie Ihre Liste ab, oder stellen Sie Elemente, die nie veralten, auf "Unbegrenzt".

::: tip
Die Prüfung erfolgt ohne Deduplizierung — jeder Durchlauf versendet erneut eine vollständige Zusammenfassung aller aktuell abgelaufenen Elemente. Wird der Befehl öfter als einmal täglich eingeplant, erhalten Prüfer doppelte Zusammenfassungen für dieselben Elemente. Einmal täglich ist daher das richtige Intervall.
:::

## Änderungshinweise

Wenn ein verfolgtes Element mit Prüfer bearbeitet und gespeichert wird, erhält der Prüfer sofort einen kurzen Hinweis ("Ein Eintrag, den Sie überprüfen sollen, wurde aktualisiert") mit einem Link zum Element. So können Prüfer guten Gewissens für Inhalte einstehen: Nichts ändert sich hinter ihrem Rücken.

Details:

- Hinweise werden nur für Elemente versendet, die ein "Verifiziert bis"-Datum tragen. "Unbegrenzt" verifizierte Elemente lösen keine aus.
- Für eigene Änderungen erhalten Sie nie einen Hinweis, nur wenn jemand anderes Ihr zugewiesenes Element ändert.
- Bei Einträgen löst jede gespeicherte Änderung den Hinweis aus.
- Bei Dateien wird der Hinweis durch inhaltlich relevante Änderungen ausgelöst, etwa eine ersetzte Datei oder geänderten Alternativtext.

![Change alert email](/screenshots/email-notifications/change-alert.png)

## Was Prüfer mit diesen E-Mails tun sollten

1. Öffnen Sie das verlinkte Element.
2. Prüfen Sie den Inhalt (bei Ablauf-Zusammenfassungen: ob er noch korrekt ist; bei Änderungshinweisen: die letzten Änderungen).
3. Setzen Sie ein neues "Verifiziert bis"-Datum, um den Inhalt zu bestätigen, siehe [Inhalte verifizieren](verifying-content.md).
