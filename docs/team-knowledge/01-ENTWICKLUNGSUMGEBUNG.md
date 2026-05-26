# Entwicklungsumgebung

## Port-Uebersicht (seit v0.16.0)

| Port | Service | Stack | Compose-File |
|------|---------|-------|--------------|
| 8082 | WordPress Dev | Dev | `docker-compose.yml` |
| 8083 | phpMyAdmin Dev | Dev | `docker-compose.yml` |
| 8086 | WordPress Stage | Stage | `docker-compose.staging.yml` |
| 8087 | phpMyAdmin Stage | Stage | `docker-compose.staging.yml` |

> Hinweis: Plan-Doc nannte urspruenglich 8084/8085. Auf diesem Entwickler-Host
> sind die durch andere Docker-Projekte belegt - Lead-Decision in v0.16.0
> verschiebt die Stage auf 8086/8087.

Beide Stacks koennen parallel laufen (getrennte Project-Namen, getrennte
Volumes, getrennte DB-Namen). Der Plugin-Mount zeigt in beiden Faellen auf
dasselbe Source-Directory, sodass Code-Aenderungen sofort in beiden Stacks
wirken (Trust-Decision T16 in `docs/architecture/24-DEV-STRECKE-PLAN-v0160.md`).

## Docker Setup - Dev-Stack

### Starten
```bash
docker compose up -d
```

### Zugang
| Service | URL | Credentials |
|---------|-----|-------------|
| WordPress | http://localhost:8082 | admin / (bei Setup festgelegt) |
| phpMyAdmin | http://localhost:8083 | wp_user / wp_pass_2025 |
| Datenbank | db:3306 | wp_user / wp_pass_2025 |

### Container
- **MariaDB 10.11**: Persistent Volume `db_data`
- **WordPress (latest)**: Plugin gemountet nach `/var/www/html/wp-content/plugins/wp-deubner-hp-services`
- **phpMyAdmin**: Datenbank-Admin

### Debug
```
WP_DEBUG=true
WP_DEBUG_LOG=true
WP_DEBUG_DISPLAY=true
```

## Stage-Stack (seit v0.16.0)

Die Stage-Site dient dem Test eines Pre-Releases von GitHub VOR der Promotion
zu Stable. Siehe `docs/team-knowledge/07-RELEASE-CHECKLIST.md` fuer den
Test-Ablauf.

### Starten
```bash
docker compose -p dhps-stage -f docker-compose.staging.yml up -d
```

### Stoppen
```bash
docker compose -p dhps-stage -f docker-compose.staging.yml down
```

### Zugang
| Service | URL | Credentials |
|---------|-----|-------------|
| WordPress Stage | http://localhost:8086 | admin / (bei Setup festgelegt) |
| phpMyAdmin Stage | http://localhost:8087 | wp_user_stage / wp_pass_stage_2025 |
| DB Stage | db:3306 (im Netzwerk dhps-stage) | wp_user_stage / wp_pass_stage_2025 |

### Container
- **MariaDB 10.11**: Volume `db_data_stage`, DB `wordpress_stage`
- **WordPress (latest)**: Volume `wp_data_stage`, Plugin-Mount identisch zum Dev-Stack
- **phpMyAdmin**: separater Stage-Container

Zusaetzliche Konstante in Stage-`wp-config.php` (per WORDPRESS_CONFIG_EXTRA):
```php
define( 'DHPS_ENV_LABEL', 'STAGE' );
```

### Schreibrechte-Hinweis (Risiko R5 v0.16.0)

Auf Windows-Hosts kann das WP-Update auf der Stage-Site an Permissions scheitern,
weil das Plugin-Verzeichnis ueber den Docker-Bind-Mount gehalten wird und der
Container-User `www-data` ggf. keine Schreibrechte hat.

Workaround:

```bash
docker exec dhps-stage-wordpress-1 \
  chown -R www-data:www-data /var/www/html/wp-content/plugins/wp-deubner-hp-services
```

Konkreten Container-Namen mit `docker ps` verifizieren - das Suffix `-1` ist
Compose-Default, kann aber variieren.

## Windows-spezifische Hinweise

### Docker exec mit MSYS_NO_PATHCONV
Windows Git Bash konvertiert Unix-Pfade automatisch. Losung:

```bash
MSYS_NO_PATHCONV=1 docker exec wordpress wp plugin list --path=/var/www/html
```

### wp eval Shell-Escaping
`wp eval` hat auf Windows Shell-Escaping-Probleme. Stattdessen Test-PHP-Dateien verwenden:

```php
// test.php im Plugin-Root
<?php
require_once 'Deubner_HP_Services.php';
// Test-Code hier
```

## Dateistruktur im Container

```
/var/www/html/
├── wp-content/
│   ├── plugins/
│   │   └── wp-deubner-hp-services/  <- Plugin (gemountet)
│   └── themes/
│       └── deubner-demo/            <- Demo-Theme (gemountet)
└── wp-config.php
```

## Test-OTA-Nummern

| Service | OTA |
|---------|-----|
| MIO | OTA-2023184382 |
| MMB | OTA-2024186296 |
| TP | OTA-2023182947 |

Diese in WordPress Admin -> Deubner Verlag -> jeweilige Service-Seite eingeben.

## Plugin aktivieren

1. Docker starten
2. http://localhost:8082/wp-admin/plugins.php
3. "Deubner HP Services" aktivieren
4. Unter "Deubner Verlag" im Admin-Menu OTA/kdnr eingeben
5. Shortcode auf beliebiger Seite einbinden: `[mio]`
