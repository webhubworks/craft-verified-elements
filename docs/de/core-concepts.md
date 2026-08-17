# Grundbegriffe

Ein kurzes Glossar der Begriffe, die im Plugin und in diesem Handbuch verwendet werden.

## Element

Crafts Wort für ein Stück Inhalt. Verified Elements arbeitet mit zwei Element-Typen:

- **Einträge**, in jeder Edition verfügbar.
- **Dateien** (Assets in Ihren Volumes), verfügbar in der Pro-Edition.

Die Verifizierung gilt nur für Elemente in Bereichen und Volumes, die ein Administrator in den Einstellungen des Plugins aktiviert hat.

## Verifiziert bis

Das Datum, bis zu dem ein Inhalt als korrekt gilt. Sie setzen es, indem Sie eine Gültigkeitsdauer wählen (7 Tage, 30 Tage, 90 Tage, 1 Jahr), ein bestimmtes Datum auswählen oder **Unbegrenzt** wählen.

## Verifizierungsstatus

Jedes verfolgte Element befindet sich in einem von zwei Zuständen:

- **Verifiziert** (türkis). Das "Verifiziert bis"-Datum liegt in der Zukunft, oder das Element ist unbegrenzt verifiziert.
- **Abgelaufen** (rot). Das "Verifiziert bis"-Datum ist überschritten. Das Element muss überprüft werden.

Der Status erscheint auf Bearbeitungsseiten, in Listenspalten, im eigenen Bereich des Plugins und in Widgets.

## Unbegrenzt

Ein "Unbegrenzt" verifiziertes Element hat kein Ablaufdatum. Es gilt dauerhaft als Verifiziert, erscheint nie in Prüflisten und löst nie Ablauf-E-Mails aus. Verwenden Sie es für Inhalte, die nicht veralten, etwa rechtliche Seiten, die über einen anderen Prozess geprüft werden.

## Bevorstehend

Ein Element, dessen "Verifiziert bis"-Datum innerhalb der nächsten 30 Tage liegt. Der Bereich des Plugins hat einen "Bevorstehend"-Filter, damit Sie handeln können, bevor Inhalte tatsächlich ablaufen.

## Prüfer

Der Craft-Benutzer, der dafür verantwortlich ist, ein Element erneut zu kontrollieren, wenn es abläuft. Ein Prüfer:

- erhält eine E-Mail-Zusammenfassung, wenn ihm zugewiesene Elemente ablaufen,
- erhält eine Hinweis-E-Mail, wenn ein zugewiesenes, verifiziertes Element geändert wird,
- sieht seine Zuweisungen im Widget "Elemente zum Prüfen" und auf seiner Kontoseite.

Elemente ohne Prüfer sind **Unbestimmt** (nicht zugewiesen). Abgelaufene, nicht zugewiesene Elemente werden an die System-E-Mail-Adresse der Website gemeldet statt an eine Person.

## Standard-Prüfer und Standard Gültigkeitsdauer

Pro Bereich (und pro Volume) können Administratoren einen Standard-Prüfer und eine Standard Gültigkeitsdauer festlegen. Neue Inhalte übernehmen diese Vorgaben automatisch beim ersten Speichern, sodass Redakteure nicht daran denken müssen, sie zu setzen.

## Website-Bezug

Auf Installationen mit mehreren Websites und der Pro-Edition wird die Verifizierung **pro Website** verfolgt: Derselbe Eintrag kann auf einer Website Verifiziert und auf einer anderen Abgelaufen sein. Ohne Unterstützung für mehrere Websites (Standard-Edition) verwaltet das Plugin nur die primäre Website.
