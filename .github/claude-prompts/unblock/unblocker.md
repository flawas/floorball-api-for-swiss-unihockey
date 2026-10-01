## Stufe unblocker (Agent: wp-unblocker)
1. Ermittle die abgebrochene Stufe und den Grund aus dem jüngsten `status:abort`-Kommentar.
2. Entscheide: kann die Rückfrage beantwortet werden (`resume`), ist der Auftrag zu gross oder gemischt (`split`),
   oder braucht es einen Menschen (`escalate`)? Beispiel: "Aufteilung in kleine Issues, Schritt 5 braucht
   Freigabe der Workflow-Änderung" -> `split`: Schritte 1-4 als Sub-Issues für Claude, Schritt 5 als Sub-Issue mit
   `needs-human`.
3. Führe die Aktion gemäss "Ergebnis" aus und poste den Marker-Kommentar.
