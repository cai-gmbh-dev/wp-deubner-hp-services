# API-Referenz: Deubner Online Services

## Aktuelle API (Legacy)

### Basis-Informationen

| Eigenschaft | Wert |
|-------------|------|
| **Base-URL** | `https://www.deubner-online.de/` |
| **Protokoll** | HTTPS (SSL/TLS) |
| **Methode** | GET |
| **Response-Format** | HTML-Fragment (kein JSON) |
| **Authentifizierung** | OTA-Nummer oder Kundennummer als GET-Parameter |

### Gemeinsame Parameter

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `ota` | string | Online-Transactions-Account Nummer (Freigabe-Nummer) |
| `kdnr` | string | Kundennummer (alternative Authentifizierung) |
| `modus` | string | Anzeigemodus (`p` = Plugin/eingebettet) |

---

### Endpoints

#### 1. MI-Online (Steuerrecht & Recht)

**Endpoint:** `einbau/mio/bin/php_inhalt.php`

| Parameter | Typ | Werte | Beschreibung |
|-----------|-----|-------|-------------|
| `ota` | string | *OTA-Nummer* | Pflicht: Freigabenummer |
| `variante` | string | `tagesaktuell`, `kategorisiert` | Darstellungsvariante |
| `anzahl` | int | z.B. `10` | Eintraege pro Seite (nur bei tagesaktuell) |
| `teasermodus` | string | | Teaser-Darstellung |
| `filter` | string | | Inhaltsfilter |
| `st_kategorie` | string | | Steuer-Kategorie |
| `modus` | string | | Anzeigemodus |

**Beispiel:**
```
GET /einbau/mio/bin/php_inhalt.php?ota=12345&variante=tagesaktuell&anzahl=10
```

---

#### 2. Merkblaetter (Fact Sheets)

**Endpoint:** `einbau/mmo/merkblattpages/php_inhalt.php`

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `ota` | string | Pflicht: Freigabenummer |
| `id_merkblatt` | string | Spezifisches Merkblatt |
| `rubrik` | string | Rubrik-Filter |
| `modus` | string | `p` = Plugin-Modus |

**Beispiel:**
```
GET /einbau/mmo/merkblattpages/php_inhalt.php?ota=12345&modus=p
```

---

#### 3. Infografiken

**Endpoint:** `einbau/mil/bin/php_inhalt.php`

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `ota` | string | Pflicht: Freigabenummer |
| `id_merkblatt` | string | Spezifische Infografik |
| `rubrik` | string | Rubrik-Filter |
| `modus` | string | `p` = Plugin-Modus |

---

#### 4. TaxPlain (Videos)

**Endpoint:** `einbau/taxplain/videopages/php_inhalt.php`

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `ota` | string | Pflicht: Freigabenummer |
| `video` | int | Video-Nummer (Einzelvideo) |
| `teasermodus` | string | Teaser-Darstellung |
| `einzelvideo` | string | Einzelvideo-ID |
| `videoliste` | string | Videolisten-Filter |
| `modus` | string | `p` = Plugin-Modus |

---

#### 5. TaxPlain Teaser

**Endpoint:** `taxplain/videopages/teaser_php.php`

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `kdnr` | string | Pflicht: Kundennummer |
| `ueberschrift` | string | Teaser-Ueberschrift |
| `teasertext` | string | Teaser-Text |
| `breite` | string | Breite des Teasers |
| `modus` | string | `standard`, `p`, `t`, `pt` |

**Teaser-Modi:**
- `standard` - Monitor mit wechselnden Video-Titelbildern inkl. Ueberschrift und Teasertext
- `p` - Nur Titelbild des aktuellen Videos
- `t` - Nur Titel des aktuellen Videos
- `pt` - Titel und Titelbild des aktuellen Videos

---

#### 6. Tax-Rechner (TaxCalc)

**Endpoint:** `webcalc/bin/php_inhalt_v2.php`

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `kdnr` | string | Pflicht: Kundennummer |
| `modus` | string | `p` = Plugin-Modus |

**Hinweis:** fsockopen-Fallback nutzt abweichende URL `php_inhalt.php` statt `php_inhalt_v2.php` (Bug im aktuellen Code).

---

#### 7. Meine Aerzteseite (InfoKombi)

**Endpoint:** `infokombi/bin/infokombi.php`

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `kdnr` | string | Pflicht: Kundennummer |
| `video` | int | Video-Nummer |
| `modus` | string | `p` = Plugin-Modus |

---

#### 8. Lexplain (Rechts-Videos)

**Endpoint:** `lexplain/bin/php_inhalt.php`

| Parameter | Typ | Beschreibung |
|-----------|-----|-------------|
| `ota` | string | Pflicht: Freigabenummer |
| `videoliste` | string | Videolisten-Filter |
| `teasermodus` | string | Teaser-Darstellung |
| `show_teaser` | int | `1` = Teaser anzeigen (default) |
| `filter` | string | Inhaltsfilter |
| `modus` | string | `p` = Plugin-Modus |

---

## Geplante neue API (Zukunft)

### Anforderungen an die neue API

Die neue API soll folgende Verbesserungen gegenueber der Legacy-API bieten:

1. **JSON-Format** statt HTML-Fragmente
2. **REST-konform** mit Standard HTTP-Statuscodes
3. **Strukturierte Daten** (Titel, Beschreibung, Datum, Bild-URL, Content)
4. **Pagination** mit Standard-Parametern (page, per_page)
5. **Versionierung** (z.B. `/api/v2/`)
6. **API-Key oder OAuth2** statt OTA-Nummern im Klartext
7. **Rate-Limiting** mit Standard-Headers
8. **Webhook-Support** fuer Content-Updates

### Vorgeschlagenes Response-Format

```json
{
  "status": "success",
  "data": {
    "items": [
      {
        "id": "12345",
        "type": "article",
        "title": "Steuertipp des Tages",
        "excerpt": "Kurztext...",
        "content": "Volltext-HTML...",
        "date": "2024-01-15T10:30:00Z",
        "category": "Einkommensteuer",
        "image_url": "https://...",
        "source_url": "https://..."
      }
    ],
    "pagination": {
      "total": 150,
      "page": 1,
      "per_page": 10,
      "total_pages": 15
    }
  },
  "meta": {
    "api_version": "2.0",
    "cache_ttl": 3600
  }
}
```

### Adapter-Pattern fuer API-Wechsel

```php
// config.php
define('DHPS_API_VERSION', 'legacy'); // oder 'v2'

// API-Factory
class DHPS_API_Factory {
    public static function create(): DHPS_API_Interface {
        switch (DHPS_API_VERSION) {
            case 'v2':
                return new DHPS_Modern_API();
            default:
                return new DHPS_Legacy_API();
        }
    }
}
```
