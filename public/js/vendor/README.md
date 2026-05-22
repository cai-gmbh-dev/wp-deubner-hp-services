# Vendor-Bundles fuer DHPS

Dieser Ordner enthaelt third-party JavaScript-Bibliotheken, die lokal mit dem
Plugin ausgeliefert werden (kein CDN aus DSGVO-Gruenden).

## Alpine.js

- **Datei:** `alpinejs-3.14.x.min.js`
- **Geplante Version:** `3.14.9` (aktuelle stable v3.x zum Stand 2026-05-22)
- **Lizenz:** MIT
- **Source:** <https://unpkg.com/alpinejs@3.14.9/dist/cdn.min.js>
- **Mirror:** <https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js>

### Download-Anweisung (manuell)

Der Specialist-Agent konnte die Datei nicht automatisch herunterladen
(Sandbox-Restriktion fuer Outbound-HTTP). Bitte den Download manuell ausfuehren:

#### Windows PowerShell

```powershell
Invoke-WebRequest `
  -Uri 'https://unpkg.com/alpinejs@3.14.9/dist/cdn.min.js' `
  -OutFile 'public/js/vendor/alpinejs-3.14.x.min.js' `
  -UseBasicParsing
```

#### macOS / Linux / Git-Bash

```bash
curl -sSL \
  -o public/js/vendor/alpinejs-3.14.x.min.js \
  https://unpkg.com/alpinejs@3.14.9/dist/cdn.min.js
```

### Verifikation

Nach dem Download Hash pruefen und in `.alpinejs-version` eintragen:

```powershell
Get-FileHash -Algorithm SHA256 public/js/vendor/alpinejs-3.14.x.min.js
```

```bash
sha256sum public/js/vendor/alpinejs-3.14.x.min.js
```

Erwartete Groesse: ca. 44 KB minified / ca. 16 KB gzipped.

### Update-Strategie

- Major-Pin auf v3.x.x (kein automatischer Major-Wechsel).
- Bei Patch-/Minor-Update: Datei neu downloaden, Hash in `.alpinejs-version`
  aktualisieren, `DHPS_ALPINE_VERSION`-Konstante in `Deubner_HP_Services.php`
  anpassen.
- Vor jedem Update: Visual-Regression-Test auf den DHPS-Service-Seiten.

## Sicherheits-Hinweis

Vendor-Files werden NICHT modifiziert. Bei Bugs upstream meldet das Team
einen Issue oder pinned auf eine fixed Patch-Version.
