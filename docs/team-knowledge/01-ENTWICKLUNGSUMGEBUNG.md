# Entwicklungsumgebung

## Docker Setup

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
