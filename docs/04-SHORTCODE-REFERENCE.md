# Shortcode-Referenz

## Uebersicht

Alle Shortcodes werden im WordPress-Editor (Text-Modus) oder in Elementor-Textfeldern eingefuegt.

**Voraussetzung:** Die jeweilige OTA-Nummer oder Kundennummer muss im Admin-Bereich unter "Deubner Verlag" konfiguriert sein.

---

## Shortcodes (Ist-Zustand v0.2.0)

### [mio] - MI-Online Steuerrecht

Zeigt tagesaktuelle Steuerrecht-Nachrichten an.

```
[mio]
[mio teasermodus="1"]
[mio variante="tagesaktuell" filter="einkommensteuer"]
[mio st_kategorie="bilanzsteuerrecht" anzahl="5"]
```

| Parameter | Standard | Beschreibung |
|-----------|----------|-------------|
| `teasermodus` | *(leer)* | Teaser-Darstellungsmodus |
| `filter` | *(leer)* | Inhaltsfilter |
| `variante` | *(Admin)* | `tagesaktuell`, `kategorisiert`, oder per Parameter |
| `modus` | *(leer)* | Anzeigemodus |
| `st_kategorie` | *(leer)* | Steuer-Kategorie |

**Admin-Einstellungen:** Deubner Verlag > Mi-Online > Steuerrecht

---

### [lxmio] - MI-Online Recht

Zeigt tagesaktuelle Rechts-Nachrichten an. Identische Parameter wie `[mio]`.

```
[lxmio]
[lxmio variante="kategorisiert"]
```

**Admin-Einstellungen:** Deubner Verlag > Mi-Online > Recht

---

### [mmb] - Merkblaetter

Zeigt Mandanten-Merkblaetter an.

```
[mmb]
[mmb id_merkblatt="123"]
[mmb rubrik="steuertipps"]
```

| Parameter | Standard | Beschreibung |
|-----------|----------|-------------|
| `id_merkblatt` | *(leer)* | Spezifisches Merkblatt anzeigen |
| `rubrik` | *(leer)* | Nach Rubrik filtern |

**Admin-Einstellungen:** Deubner Verlag > Merkblaetter

---

### [mil] - Infografiken

Zeigt Infografiken an. Identische Parameter wie `[mmb]`.

```
[mil]
[mil id_merkblatt="456" rubrik="steuertipps"]
```

**Admin-Einstellungen:** Deubner Verlag > Infografiken

---

### [tp] - TaxPlain Videos

Zeigt Steuer-Erklaervideos an.

```
[tp]
[tp teasermodus="1"]
[tp einzelvideo="789"]
[tp videoliste="steuererklaerung"]
```

| Parameter | Standard | Beschreibung |
|-----------|----------|-------------|
| `teasermodus` | `0` | Teaser-Darstellung |
| `einzelvideo` | `0` | Einzelnes Video anzeigen |
| `videoliste` | *(leer)* | Videolisten-Filter |

**Hinweis:** Unterstuetzt URL-Parameter `?video=N` fuer Direkt-Links.

**Admin-Einstellungen:** Deubner Verlag > Tax-Videos

---

### [tpt] - TaxPlain Teaser

Zeigt einen Video-Teaser an. Alle Parameter werden im Admin konfiguriert.

```
[tpt]
```

**Admin-Einstellungen:** Deubner Verlag > Tax-Videos (unterer Bereich)

---

### [tc] - Tax-Rechner

Zeigt den Online-Steuerrechner an.

```
[tc]
```

Keine Shortcode-Parameter. Konfiguration nur im Admin.

**Admin-Einstellungen:** Deubner Verlag > Tax-Rechner

---

### [maes] - Meine Aerzteseite

Zeigt das Aerzte-Informationsportal an.

```
[maes]
```

Keine Shortcode-Parameter. Konfiguration nur im Admin.

**Admin-Einstellungen:** Deubner Verlag > Aerzte-Info

---

### [lp] - Lexplain

Zeigt Rechts-Informationsvideos an.

```
[lp]
[lp videoliste="arbeitsrecht"]
[lp teasermodus="1" show_teaser="0"]
[lp filter="mietrecht"]
```

| Parameter | Standard | Beschreibung |
|-----------|----------|-------------|
| `videoliste` | `0` | Videolisten-Filter |
| `teasermodus` | `0` | Teaser-Darstellung |
| `show_teaser` | `1` | Teaser anzeigen (1) oder nicht (0) |
| `filter` | *(leer)* | Inhaltsfilter |

**Admin-Einstellungen:** Deubner Verlag > Lexplain

---

## Geplante Erweiterungen (ab v0.5.0)

### Neue universelle Parameter fuer alle Shortcodes

| Parameter | Werte | Beschreibung |
|-----------|-------|-------------|
| `layout` | `default`, `card`, `grid`, `list`, `compact` | Layout-Variante |
| `class` | CSS-Klassenname | Eigene CSS-Klasse hinzufuegen |
| `cache` | Sekunden (z.B. `3600`) | Cache-Dauer |
| `demo` | `true`, `false` | Demo-Modus erzwingen |

**Beispiel:**
```
[mio layout="card" class="my-custom-style" cache="1800"]
```
