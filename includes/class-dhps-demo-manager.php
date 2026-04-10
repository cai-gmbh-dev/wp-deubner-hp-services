<?php
/**
 * Demo-Manager fuer den Deubner Homepage Service.
 *
 * Verwaltet den Demo-Modus fuer alle 9 Deubner-Services. Ermoeglicht
 * zeitlich begrenzte Aktivierung von Demo-Credentials, sichert dabei
 * bestehende echte Zugangsdaten und stellt diese nach Ablauf oder
 * manueller Deaktivierung wieder her.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Demo_Manager
 *
 * Steuert Aktivierung, Deaktivierung und Ablauf-Pruefung von Demo-Zugaengen
 * fuer alle registrierten Deubner-Services. Der Demo-Zustand wird in einer
 * einzelnen WordPress-Option als serialisiertes Array gespeichert.
 *
 * @since   0.6.0
 * @package Deubner Homepage-Service
 */
class DHPS_Demo_Manager {

	/**
	 * Standard-Demo-Dauer in Tagen.
	 *
	 * @since 0.6.0
	 * @var int
	 */
	const DEFAULT_DEMO_DURATION = 30;

	/**
	 * Name der WordPress-Option fuer den Demo-Zustand.
	 *
	 * @since 0.6.0
	 * @var string
	 */
	const OPTION_DEMO_STATE = 'dhps_demo_state';

	/**
	 * Name der WordPress-Option fuer die konfigurierbare Demo-Dauer.
	 *
	 * @since 0.6.0
	 * @var string
	 */
	const OPTION_DEMO_DURATION = 'dhps_demo_duration_days';

	/**
	 * Demo-Credentials fuer alle 9 Services.
	 *
	 * Platzhalter-Werte, die spaeter durch echte Demo-Nummern vom Verlag
	 * ersetzt werden koennen. Filterbar ueber 'dhps_demo_credentials'.
	 *
	 * @since 0.6.0
	 * @var array
	 */
	private static $demo_credentials = array(
		'mio'   => 'DEMO-MIO-2025',
		'lxmio' => 'DEMO-LXMIO-2025',
		'mmb'   => 'DEMO-MMB-2025',
		'mil'   => 'DEMO-MIL-2025',
		'tp'    => 'DEMO-TP-2025',
		'tpt'   => 'DEMO-TPT-2025',
		'tc'    => 'DEMO-TC-2025',
		'maes'  => 'DEMO-MAES-2025',
		'lp'    => 'DEMO-LP-2025',
	);

	/**
	 * Gibt die Demo-Credentials zurueck (filterbar).
	 *
	 * Ermoeglicht es Dritten, Demo-Credentials ueber den Filter
	 * 'dhps_demo_credentials' anzupassen oder zu ergaenzen.
	 *
	 * @since 0.6.0
	 *
	 * @return array Assoziatives Array mit Service-Slug als Key und Demo-Credential als Value.
	 */
	private function get_demo_credentials(): array {
		/**
		 * Filtert die Demo-Credentials fuer alle Services.
		 *
		 * @since 0.6.0
		 *
		 * @param array $demo_credentials Assoziatives Array (slug => credential).
		 */
		return apply_filters( 'dhps_demo_credentials', self::$demo_credentials );
	}

	/**
	 * Laedt den gespeicherten Demo-Zustand aus der Datenbank.
	 *
	 * @since 0.6.0
	 *
	 * @return array Assoziatives Array des Demo-Zustands.
	 */
	private function get_state(): array {
		$state = get_option( self::OPTION_DEMO_STATE, array() );

		if ( ! is_array( $state ) ) {
			return array();
		}

		return $state;
	}

	/**
	 * Speichert den Demo-Zustand in der Datenbank.
	 *
	 * @since 0.6.0
	 *
	 * @param array $state Assoziatives Array des Demo-Zustands.
	 *
	 * @return bool True bei Erfolg, false bei Fehler.
	 */
	private function save_state( array $state ): bool {
		return update_option( self::OPTION_DEMO_STATE, $state );
	}

	/**
	 * Prueft ob Demo fuer einen Service verfuegbar ist.
	 *
	 * Verfuegbar bedeutet: Demo-Credentials sind definiert UND der Service
	 * ist noch nicht mit echten (Nicht-Demo) Credentials aktiv.
	 *
	 * @since 0.6.0
	 *
	 * @param string $slug Service-Slug (z.B. 'mio', 'tp').
	 *
	 * @return bool True wenn Demo verfuegbar, false sonst.
	 */
	public function is_demo_available( string $slug ): bool {
		$credentials = $this->get_demo_credentials();

		// Keine Demo-Credentials fuer diesen Service definiert.
		if ( empty( $credentials[ $slug ] ) ) {
			return false;
		}

		$service = DHPS_Service_Registry::get_service( $slug );

		// Unbekannter Service.
		if ( null === $service ) {
			return false;
		}

		// Demo bereits aktiv - dann ist sie nicht "verfuegbar" im Sinne von "kann aktiviert werden".
		if ( $this->is_demo_active( $slug ) ) {
			return false;
		}

		// Pruefen ob bereits echte Credentials vorhanden sind.
		$current_value = get_option( $service['auth_option'], '' );

		// Wenn ein nicht-leerer Wert vorhanden ist, der kein Demo-Wert ist, hat der
		// Service bereits echte Credentials - Demo ist trotzdem verfuegbar (zum Testen).
		// Wenn der Wert leer ist, ist Demo ebenfalls verfuegbar.
		// Wenn der Wert mit "DEMO-" beginnt aber keine aktive Demo im State ist,
		// handelt es sich um eine ungueltige Demo-Nummer - Demo ist verfuegbar.
		return true;
	}

	/**
	 * Prueft ob der Demo-Modus fuer einen Service gerade aktiv ist.
	 *
	 * @since 0.6.0
	 *
	 * @param string $slug Service-Slug (z.B. 'mio', 'tp').
	 *
	 * @return bool True wenn Demo aktiv und nicht abgelaufen, false sonst.
	 */
	public function is_demo_active( string $slug ): bool {
		$state = $this->get_state();

		if ( empty( $state[ $slug ] ) || empty( $state[ $slug ]['active'] ) ) {
			return false;
		}

		// Pruefen ob die Demo abgelaufen ist.
		if ( $this->get_days_remaining( $slug ) <= 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Aktiviert den Demo-Modus fuer einen Service.
	 *
	 * Fuehrt folgende Schritte aus:
	 * 1. Backup des aktuellen Auth-Werts (falls vorhanden)
	 * 2. Demo-Credential in die Auth-Option schreiben
	 * 3. State mit Aktivierungszeitpunkt speichern
	 *
	 * @since 0.6.0
	 *
	 * @param string $slug Service-Slug (z.B. 'mio', 'tp').
	 *
	 * @return bool True bei Erfolg, false bei Fehler.
	 */
	public function activate_demo( string $slug ): bool {
		$credentials = $this->get_demo_credentials();

		// Keine Demo-Credentials fuer diesen Service.
		if ( empty( $credentials[ $slug ] ) ) {
			return false;
		}

		$service = DHPS_Service_Registry::get_service( $slug );

		// Unbekannter Service.
		if ( null === $service ) {
			return false;
		}

		// Demo ist bereits aktiv.
		if ( $this->is_demo_active( $slug ) ) {
			return false;
		}

		$auth_option = $service['auth_option'];

		// 1. Backup des aktuellen Auth-Werts.
		$original_value = get_option( $auth_option, '' );

		// 2. Demo-Credential in die Auth-Option schreiben.
		$demo_value = sanitize_text_field( $credentials[ $slug ] );
		update_option( $auth_option, $demo_value );

		// 3. State speichern.
		$state          = $this->get_state();
		$state[ $slug ] = array(
			'active'         => true,
			'activated_at'   => time(),
			'original_value' => sanitize_text_field( $original_value ),
		);

		$this->save_state( $state );

		return true;
	}

	/**
	 * Deaktiviert den Demo-Modus fuer einen Service.
	 *
	 * Stellt den originalen Auth-Wert wieder her (oder leert die Option,
	 * wenn kein Backup vorhanden ist) und bereinigt den State.
	 *
	 * @since 0.6.0
	 *
	 * @param string $slug Service-Slug (z.B. 'mio', 'tp').
	 *
	 * @return void
	 */
	public function deactivate_demo( string $slug ): void {
		$service = DHPS_Service_Registry::get_service( $slug );

		if ( null === $service ) {
			return;
		}

		$state       = $this->get_state();
		$auth_option = $service['auth_option'];

		// 1. Original-Wert wiederherstellen oder leeren.
		if ( isset( $state[ $slug ]['original_value'] ) && '' !== $state[ $slug ]['original_value'] ) {
			update_option( $auth_option, sanitize_text_field( $state[ $slug ]['original_value'] ) );
		} else {
			update_option( $auth_option, '' );
		}

		// 2. State bereinigen.
		unset( $state[ $slug ] );
		$this->save_state( $state );
	}

	/**
	 * Gibt die Anzahl verbleibender Demo-Tage zurueck.
	 *
	 * Gibt 0 zurueck wenn die Demo abgelaufen oder nicht aktiv ist.
	 *
	 * @since 0.6.0
	 *
	 * @param string $slug Service-Slug (z.B. 'mio', 'tp').
	 *
	 * @return int Verbleibende Tage (0 wenn abgelaufen oder nicht aktiv).
	 */
	public function get_days_remaining( string $slug ): int {
		$state = $this->get_state();

		if ( empty( $state[ $slug ] ) || empty( $state[ $slug ]['active'] ) ) {
			return 0;
		}

		$activated_at = (int) $state[ $slug ]['activated_at'];
		$duration     = $this->get_demo_duration();
		$expires_at   = $activated_at + ( $duration * DAY_IN_SECONDS );
		$now          = time();

		if ( $now >= $expires_at ) {
			return 0;
		}

		$remaining_seconds = $expires_at - $now;

		return (int) ceil( $remaining_seconds / DAY_IN_SECONDS );
	}

	/**
	 * Gibt den Status eines Service zurueck.
	 *
	 * Moegliche Rueckgabewerte:
	 * - 'active':   Echte Credentials vorhanden (nicht-leer und nicht-Demo).
	 * - 'demo':     Demo aktiv und nicht abgelaufen.
	 * - 'inactive': Keine Credentials oder ungueltige Demo-Nummer.
	 *
	 * @since 0.6.0
	 *
	 * @param string $slug Service-Slug (z.B. 'mio', 'tp').
	 *
	 * @return string Status-String: 'active', 'demo' oder 'inactive'.
	 */
	public function get_service_status( string $slug ): string {
		$service = DHPS_Service_Registry::get_service( $slug );

		if ( null === $service ) {
			return 'inactive';
		}

		// Pruefen ob Demo aktiv und nicht abgelaufen ist.
		if ( $this->is_demo_active( $slug ) ) {
			return 'demo';
		}

		// Aktuellen Auth-Wert pruefen.
		$current_value = get_option( $service['auth_option'], '' );

		// Leerer Wert = inaktiv.
		if ( '' === $current_value ) {
			return 'inactive';
		}

		// Wert beginnt mit "DEMO-" aber Demo ist nicht im State aktiv = ungueltig.
		if ( 0 === strpos( $current_value, 'DEMO-' ) ) {
			$state = $this->get_state();
			if ( empty( $state[ $slug ]['active'] ) ) {
				return 'inactive';
			}
		}

		return 'active';
	}

	/**
	 * Gibt die Statusse aller Services zurueck.
	 *
	 * Liefert fuer jeden registrierten Service den Status, die verbleibenden
	 * Demo-Tage und den Anzeigenamen.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string, array{status: string, days_remaining: int, name: string}> Assoziatives Array aller Service-Statusse.
	 */
	public function get_all_statuses(): array {
		$services = DHPS_Service_Registry::get_services();
		$statuses = array();

		foreach ( $services as $slug => $service ) {
			$statuses[ $slug ] = array(
				'status'         => $this->get_service_status( $slug ),
				'days_remaining' => $this->get_days_remaining( $slug ),
				'name'           => $service['name'],
			);
		}

		return $statuses;
	}

	/**
	 * Prueft und deaktiviert abgelaufene Demos.
	 *
	 * Iteriert ueber alle aktiven Demo-Eintraege im State und deaktiviert
	 * diejenigen, deren Laufzeit abgelaufen ist. Wird beim Plugin-Init aufgerufen.
	 *
	 * @since 0.6.0
	 *
	 * @return void
	 */
	public function check_expired_demos(): void {
		$state = $this->get_state();

		if ( empty( $state ) ) {
			return;
		}

		foreach ( $state as $slug => $entry ) {
			if ( empty( $entry['active'] ) ) {
				continue;
			}

			// Pruefen ob die Demo abgelaufen ist.
			$activated_at = (int) $entry['activated_at'];
			$duration     = $this->get_demo_duration();
			$expires_at   = $activated_at + ( $duration * DAY_IN_SECONDS );
			$now          = time();

			if ( $now >= $expires_at ) {
				$this->deactivate_demo( $slug );
			}
		}
	}

	/**
	 * Gibt die konfigurierte Demo-Dauer in Tagen zurueck.
	 *
	 * Liest den Wert aus der WordPress-Option 'dhps_demo_duration_days'.
	 * Faellt auf den Standard von 30 Tagen zurueck, wenn die Option
	 * nicht gesetzt oder ungueltig ist.
	 *
	 * @since 0.6.0
	 *
	 * @return int Demo-Dauer in Tagen.
	 */
	public function get_demo_duration(): int {
		$duration = get_option( self::OPTION_DEMO_DURATION, self::DEFAULT_DEMO_DURATION );
		$duration = absint( $duration );

		if ( $duration < 1 ) {
			return self::DEFAULT_DEMO_DURATION;
		}

		return $duration;
	}
}
