## Stufe architect (Agent: wp-architect)
1. Lies ggf. den debugger-Kommentar. wp-architect analysiert die Anforderung und schreibt eine kurze
   technische Spec: betroffene Dateien, Ansatz, Risiken.
2. Stufe die Komplexität ein: `simple` (Tippfehler, Text, CSS, Einzeiler), `moderate` oder `complex`.
   `complex` oder eine nicht eindeutige Spec -> Abbruchregel.
3. Ändere und committe KEINEN Code. Kommentar mit erster Zeile
   `<!-- sfa-stage:architect status:ok complexity:<simple|moderate> -->` und der Spec.
