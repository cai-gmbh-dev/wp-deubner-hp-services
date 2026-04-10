# Frontend JavaScript

## Uebersicht

Vanilla JavaScript (kein jQuery), konditionell geladen pro Service-Template.

## JavaScript-Dateien

| Datei | Service | Groesse | Registrierung |
|-------|---------|---------|---------------|
| `public/js/dhps-mio.js` | MIO/LXMIO | ~12KB | `wp_register_script` |
| `public/js/dhps-mmb.js` | MMB | ~10KB | `wp_register_script` |
| `public/js/dhps-tp.js` | TP | ~8KB | `wp_register_script` |

Scripts werden im Template per `wp_enqueue_script()` eingebunden.

## MIO JavaScript (dhps-mio.js)

### Funktionen
- **AJAX News-Loading**: Paginierung, Filterung, Suche
- **Akkordeon**: News-Artikel auf/zuklappen
- **Print**: Einzelartikel drucken
- **IntersectionObserver**: Lazy-Loading fuer Paginierung

### AJAX-Parameter
```javascript
{
    action: 'dhps_load_news',
    nonce: container.dataset.nonce,
    service_tag: 'mio',
    page: currentPage,
    search: searchInput.value,
    month: monthFilter.value,
    year: yearFilter.value,
    rubriken: rubrikSelect.value,
    zielgruppen: zielgruppenSelect.value
}
```

### DOM-Integration
- Container: `[data-dhps-news-container]`
- AJAX-Params: `data-ajax-params` (JSON)
- Nonce: `data-nonce`

## MMB JavaScript (dhps-mmb.js)

### Funktionen
- **Dual-Akkordeon**: Rubrik-Ebene + Merkblatt-Ebene
- **AJAX-Suche**: Echtzeit-Suche mit Debounce
- **Filter-Leiste**: Rubrik-Buttons
- **PDF-Download**: Proxy-Link-Generierung

### AJAX-Parameter (Suche)
```javascript
{
    action: 'dhps_mmb_search',
    nonce: container.dataset.nonce,
    search: searchInput.value,
    service_tag: 'mmb'
}
```

## TP JavaScript (dhps-tp.js)

### Funktionen
- **Lazy-Load Videos**: iframes erst bei Klick laden
- **Kategorie-Filter**: Buttons filtern Video-Grid
- **Compact-Akkordeon**: Fuer Compact-Layout
- **Video-Modal**: Iframe-src per AJAX holen

### AJAX-Parameter (Video)
```javascript
{
    action: 'dhps_tp_video_src',
    nonce: container.dataset.nonce,
    video_slug: video.dataset.videoSlug,
    poster_url: video.dataset.posterUrl,
    v_modus: video.dataset.vModus
}
```

### Sicherheit
- `video_slug` und `poster_url` in data-Attributen
- Tatsaechliche iframe-src wird per AJAX-Proxy generiert
- kdnr wird NIEMALS im Frontend exponiert

## Asset-Loading-Strategie

1. CSS global registriert und sofort geladen (Design Tokens + Base + Frontend)
2. JS global registriert, aber NUR im Template eingebunden
3. Kein Inline-JavaScript
4. Keine externen Abhaengigkeiten (kein jQuery, kein Bootstrap JS)
5. Nonce per `data-nonce`-Attribut uebergeben
