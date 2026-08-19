# FAQ und Problemlösungen

> Entwurf. Diese Liste im Lauf der Zeit um echte Support-Fragen erweitern.

## Ich sehe das Verifizierungs-Panel an einem Eintrag nicht

Prüfen Sie in dieser Reihenfolge:

1. Ist der **Bereich des Eintrags aktiviert**, unter **Verifizierung → Einstellungen → Einträge** (auf der Website, die Sie bearbeiten)?
2. Haben Sie die Berechtigung **"Einträge verifizieren"**? Ohne sie ist das Panel ausgeblendet (der Status erscheint weiterhin in den Metadaten).
3. Auf einer Installation mit mehreren Websites und der Lite-Edition wird nur die **primäre Website** verfolgt.
4. Verschachtelte Einträge in Matrix-Feldern sind nicht verifizierbar, nur reguläre Einträge in Bereichen.

## Ich sehe den Verifizierungs-Bereich nicht in der Navigation

Sie benötigen die Berechtigung **"Zugriff auf Verifizierung"**. Wenden Sie sich an einen Administrator.

## Warum hat ein neuer Eintrag einen Prüfer und ein Datum, das ich nie gesetzt habe?

Für den Bereich sind eine **Standard Gültigkeitsdauer** und ein **Standard-Prüfer** konfiguriert. Sie werden beim ersten Speichern automatisch angewendet. Beides können Sie im Verifizierungs-Panel jederzeit überschreiben.

## Niemand hat eine E-Mail erhalten, obwohl Inhalte abgelaufen sind

1. Hat das abgelaufene Element einen **Prüfer**? Nicht zugewiesene Elemente werden nur an die System-E-Mail-Adresse der Website gemeldet. Prüfen Sie den Filter **Unbestimmt**.
2. Ist die **Ablaufprüfung** gelaufen, seit das Element abgelaufen ist? Zusammenfassungen werden versendet, wenn die geplante Prüfung oder Crafts routinemäßige Wartung läuft. Fragen Sie Ihren Entwickler, ob `php craft verified-elements/check-expired-verifications` eingeplant ist.
3. Kann Ihre Website überhaupt E-Mails versenden? Testen Sie das in Craft unter **Einstellungen → E-Mail**.

## Ich erhalte immer wieder dieselbe Zusammenfassungs-E-Mail

Zusammenfassungen wiederholen sich, solange zugewiesene Elemente abgelaufen bleiben. Prüfen Sie die Elemente und setzen Sie ein neues "Verifiziert bis"-Datum, um die Erinnerungen zu beenden. Für Inhalte, die nie veralten, wählen Sie **Unbegrenzt**.

## Was bedeutet "Unbegrenzt" in der Spalte "Verifiziert bis"?

Es ist kein Ablaufdatum gesetzt. Das Element gilt als Verifiziert und läuft nie ab. Auch Elemente in aktivierten Bereichen, die nie ausdrücklich verifiziert wurden, erscheinen als Unbegrenzt.

TODO: Decide whether never-touched elements should be visually distinguishable from deliberately indefinite ones, and document the answer.

## Können zwei Personen dasselbe Element prüfen?

Jedes Element hat (pro Website) genau einen Prüfer. Für eine geteilte Verantwortung legen Sie einen Craft-Benutzer mit einem Team-Postfach an (zum Beispiel "content-team@...") und weisen diesen zu.

## Wird ein Eintrag durch das Verifizieren veröffentlicht oder verändert?

Nein. Die Verifizierungsdaten liegen neben Ihren Inhalten. Das Setzen eines Datums oder Prüfers speichert das Element zwar, verändert aber weder seine Felder noch seinen Status oder seine Veröffentlichungsdaten.

## Wir haben eine neue Website angelegt. Warum wird dort nichts verfolgt?

Bereiche müssen pro Website aktiviert werden: Öffnen Sie **Verifizierung → Einstellungen → Einträge**, wechseln Sie auf den Tab der neuen Website und aktivieren Sie sie. Volume-Einstellungen werden automatisch auf neue Websites übertragen. Die Verfolgung mehrerer Websites erfordert die Pro-Edition.

## An wen kann ich mich für Hilfe wenden?

Support: <support@webhub.de>. Fehlermeldungen: die GitHub-Issues-Seite des Plugins.

TODO: Add links to the published Plugin Store page and issue tracker.
