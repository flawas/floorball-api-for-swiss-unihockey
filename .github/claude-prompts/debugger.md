## Stufe debugger (Agent: wp-debugger)
1. Entscheide, ob das Issue ein Bug ist. Wenn nein: Kommentar `<!-- sfa-stage:debugger status:ok -->` mit
   "Kein Bug, übersprungen" und stoppen.
2. Sonst: wp-debugger ermittelt die Ursache mit Belegen (Datei:Zeile). Ist sie nicht eindeutig -> Abbruchregel.
3. Ändere und committe KEINEN Code. Kommentar `<!-- sfa-stage:debugger status:ok -->` mit Ursache und Belegen.
