## Stufe architect (Agent: wp-architect) – Feature Request
1. Es gibt keine debugger-Stufe. wp-architect beurteilt stattdessen den Scope:
   - Passt das Feature zum Zweck des Plugins (Swiss-Unihockey-Daten per Shortcode/Admin anzeigen)?
   - Liefert die Swiss-Unihockey-API die nötigen Daten? Prüfe per Client-Code bzw. `php verify_api.php`.
     Neue API-Aufrufe laufen nur über `Swiss_Floorball_API_Client`.
   - Rückwärtskompatibilität: bestehende Shortcodes, Attribute und Optionen (`swissfloorball_*`) bleiben unverändert.
   - Keine neuen Abhängigkeiten (Composer/npm), kein Build-Schritt.
2. Schreibe eine kurze technische Spec: betroffene Dateien, Ansatz, Risiken, neue/geänderte Shortcode-Attribute,
   UI-Texte (übersetzbar), Auswirkungen auf Cache und Admin-Seiten.
3. Stufe die Komplexität ein: `simple`, `moderate` oder `complex`. Bei `complex`, unklaren oder widersprüchlichen
   Anforderungen -> Abbruchregel; schlage im Abbruchkommentar vor, wie das Feature in kleinere Requests
   aufgeteilt werden kann.
4. Ändere und committe KEINEN Code. Kommentar mit erster Zeile
   `<!-- sfa-stage:architect status:ok complexity:<simple|moderate> -->` und der Spec.
