# Changelog v0.17.2 - TP/TPT/LP-Adapter (dritter Migrations-Block)

## Stand: 2026-06-04

## Mission

Fortsetzung der inkrementellen Datenmodell-Migration. Nach MAES (v0.17.0) und MMB/MIL (v0.17.1) folgen jetzt **TP, TPT und LP**. Damit sind **6 von 9** Hauptservices auf das einheitliche Datenmodell migriert.

## Strategie: Option C

- **1 TP-Adapter fuer tp + lp** (gleiche Instance, Service-Tag entscheidet im Item-Mapping)
- **Separate TPT-Adapter-Klasse** (Single-Video-Output strukturell anders als TP-Featured+Categories)
- **LP nutzt TP-Templates** ueber `dhps_template_fallbacks`-Filter, kein eigener Patch
- Adapter-Strategie nach **Output-Shape**, nicht nach Parser-Vererbung

## Hauptaenderungen

### Phase 0 (Lead): Geteilter Helper

`includes/dhps-tp-content-helpers.php` (NEU):

- `dhps_tp_item_to_legacy_video( DHPS_Content_Item $item ): array`
- Wandelt ContentItem (type=video) zurueck in Legacy-Video-Shape (8 Felder: video_id, titel, teaser, datum, video_slug, poster_url, v_modus, service)
- Genutzt von 5 Templates (2 TP + 3 TPT) -> EINZIGE Mapping-Stelle, Schema-Drift-Tests einfach
- Bootstrap-Include analog v0.17.1-Pattern

### F1: TP-Adapter (270 LOC)

`includes/class-dhps-tp-adapter.php` (NEU):

- `final class DHPS_TP_Adapter implements DHPS_Content_Adapter_Interface`
- **Featured-Pattern**: featured_video als regulaeres Item type='video' mit `meta.is_featured=true`
- **Categories**: jedes Video als ContentItem mit `category={cat_idx}`-String
- Item-ID Convention:
  - Featured: `{service}-video-featured-{video_id}`
  - Category: `{service}-video-{cat_idx}-{video_id}`
- Item-meta: video_id, datum (MM/YY-String), v_modus, mandantenvideo_service, is_featured
- Item-image: `{url: poster_url, alt: titel}`
- Item-media: `{kind: 'video', slug, poster, params: {v_modus, mandantenvideo_service}}`
- Collection-Meta:
  - `featured_video_id` (Lookup-Key)
  - `categories_order`, `categories_meta` (Lookup)
  - `total_videos` (inkl. Featured), `total_categories`
  - `video_service` ('taxplain' fuer tp, 'lexplain' fuer lp)
- Registriert fuer **tp + lp**

**F1-Tests: 12/12 PASS**

#### TP-Template-Migration (2 Templates)

- `public/views/services/tp/default.php`: Pseudo-Rebuild-Pattern, Render-Code bytewise unveraendert
- `public/views/services/tp/card.php`: analog
- `public/views/services/tp/compact.php`: **UNVERAENDERT** - Tech-Debt v0.14.3 (initCompactAccordion-Spawn-Risiko), TD-V0172-1 dokumentiert

### F2: TPT-Adapter (200 LOC)

`includes/class-dhps-tpt-adapter.php` (NEU):

- `final class DHPS_TPT_Adapter implements DHPS_Content_Adapter_Interface`
- **Single-Video-Pattern**: mappet `video` (Single-Object) -> 0 oder 1 ContentItem
- Item-ID: `tpt-video-teaser-{video_id}`
- Item-meta: video_id, datum, v_modus, mandantenvideo_service
- Collection-Meta:
  - `tpt_config` (1:1 aus Modules-Filter `dhps_pipeline_data_tpt`, enthaelt ueberschrift + teasertext)
  - `total_videos` (0 oder 1)
  - `video_service` ('taxplain' fix)
- Defense-in-Depth: Pipeline-Patch v0.17.0 ruft `dhps_pipeline_data_tpt` VOR Adapter -> Adapter sieht IMMER angereicherte Daten

**F2-Tests: 12/12 PASS**

#### TPT-Template-Migration (3 Templates)

- `public/views/services/tpt/default.php`: Pseudo-Rebuild-Pattern
- `public/views/services/tpt/card.php`: analog
- `public/views/services/tpt/compact.php`: analog (TPT-compact ist OK weil nur 1 ContentCard, kein JS-Spawn-Risiko)

### LP: keine eigene Klasse, kein eigenes Template

- LP-Pipeline-Aufruf `render_service('lp', ...)` -> TP-Adapter wird gefunden (registriert fuer 'lp')
- Adapter ruft mit `$service='lp'`, Items haben `service='lp'`
- TP-Templates via `dhps_template_fallbacks`-Filter
- Recht-Branding `dhps-service--lp` + ContentCard-Service-Hook

## Trust-Decisions T30-T34 (kumulativ 34)

| # | Decision | Begruendung |
|---|----------|-------------|
| T30 | Adapter nach Output-Shape, nicht nach Parser-Vererbung | TPT-Single != TP-Featured+Categories - eigene Klasse vermeidet Verzweigungen |
| T31 | Featured als regulaeres Item mit meta.is_featured | Whitelist (`video|news|document|tax_date|generic`) erlaubt kein `featured` |
| T32 | tp/compact.php UNVERAENDERT (TD-V0172-1) | initCompactAccordion-JS spawnt Player dynamisch, Migration braucht Pipeline-Refactor |
| T33 | Datum als MM/YY-String im meta (nicht DateTimeImmutable) | TP-Parser liefert kein Tag, Rehydration verlustig (TD-V0172-2) |
| T34 | Lead-Phase-0 Helper VOR F1/F2 | Geteilter Code (~30 LOC) vermeidet Specialist-Coordination |

## Backward Compatibility

**Vollstaendig BC**:

- 9 Parser unveraendert
- tp/compact.php unveraendert (Tech-Debt bewusst)
- LP-Pipeline-Aufruf rendert weiter ueber TP-Templates
- 5 modifizierte Templates haben Render-Code bytewise unveraendert (Pseudo-Rebuild oben einfuegt, bestehender Code darunter unangetastet)
- 6 Adapter aktiv: tp/tpt/lp/mmb/mil/maes

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/28-TP-TPT-LP-ADAPTER-PLAN-v0172.md` | Discovery + Schema-Vertrag |
| `docs/project/49-CHANGELOG-v0172.md` | (dieses Dokument) |
| `includes/dhps-tp-content-helpers.php` | Helper `dhps_tp_item_to_legacy_video` |
| `includes/class-dhps-tp-adapter.php` | TP-Adapter (F1) |
| `includes/class-dhps-tpt-adapter.php` | TPT-Adapter (F2) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.17.1 -> 0.17.2, Helper-require, 3 neue Adapter-Reg (tp/lp/tpt) |
| `README.md` | Version-Bump |
| `public/views/services/tp/default.php` | Pseudo-Rebuild-Block |
| `public/views/services/tp/card.php` | Pseudo-Rebuild-Block |
| `public/views/services/tpt/default.php` | Pseudo-Rebuild-Block |
| `public/views/services/tpt/card.php` | Pseudo-Rebuild-Block |
| `public/views/services/tpt/compact.php` | Pseudo-Rebuild-Block |
| `MEMORY.md` | MILESTONE 20 + 7 v0.17.2 Implementation-Notes |

## Migrations-Status nach v0.17.2

| Service | Adapter | Templates migriert |
|---------|---------|---------------------|
| MAES | DHPS_MAES_Adapter | 3 (v0.17.0) |
| MMB | DHPS_MMB_Adapter | 3 (v0.17.1) |
| MIL | DHPS_MMB_Adapter (geteilt) | erbt MMB via Fallback |
| TP | DHPS_TP_Adapter | 2 (default+card, compact Tech-Debt) |
| TPT | DHPS_TPT_Adapter | 3 (v0.17.2) |
| LP | DHPS_TP_Adapter (geteilt) | erbt TP via Fallback |
| MIO | offen | offen (v0.17.3) |
| LXMIO | offen | offen (v0.17.3, erbt MIO) |
| TC | offen | offen (v0.17.4) |

**6 von 9 Hauptservices migriert** (LXMIO+MIO+TC offen).

## Tech-Debt-Tickets v0.17.x

- **TD-V0172-1**: tp/compact.php Collection-Migration (initCompactAccordion-Refactor noetig)
- **TD-V0172-2**: Datum-Normalisierung MM/YY -> ISO im DTO
- **TD-V0171-2** (offen): MMB-AJAX-Handler auf Adapter umstellen

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.17.3** | MIO-Adapter (Tax-Dates Sondertyp, LXMIO erbt) |
| **v0.17.4** | TC-Adapter + Cleanup |
| **v0.18.0** | Legacy-Pfad entfernen (nach allen Migrationen) |

## Bilanz v0.17.2

- **6 Adapter aktiv** (tp/tpt/lp/mmb/mil/maes)
- **F1+F2-Tests: 24/24 PASS**
- **5 Trust-Decisions T30-T34** (kumulativ 34)
- **0 BC-Bruch** (Render-Code bytewise unveraendert in 5 Templates)
- Schema-Vertrag-Vorgehen **11x in Folge** ohne Critical-Drift
