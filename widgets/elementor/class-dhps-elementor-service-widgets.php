<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Konkrete Elementor-Widget-Klassen fuer alle 9 Deubner-Services.
 *
 * Jede Klasse erweitert DHPS_Elementor_Widget_Base und definiert
 * lediglich den Service-Key sowie ein passendes Elementor-Icon.
 * Die gesamte Logik (Controls, Rendering) steckt in der Basisklasse.
 *
 * HINWEIS: Diese Datei liegt ausserhalb von includes/ und wird NICHT
 * vom Autoloader geladen. Sie wird manuell von DHPS_Elementor::register_widgets()
 * eingebunden. Daher entfaellt der ABSPATH-Check.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Widgets/Elementor
 * @since      0.7.0
 */

/**
 * Class DHPS_Elementor_Widget_MIO
 *
 * Elementor-Widget fuer den Service MI-Online Steuerrecht.
 *
 * @since   0.7.0
 * @package Deubner Homepage-Service
 */
class DHPS_Elementor_Widget_MIO extends DHPS_Elementor_Widget_Base {

	/**
	 * Gibt den Service-Key zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Service-Key.
	 */
	protected function get_service_key(): string {
		return 'mio';
	}

	/**
	 * Gibt das Icon fuer das Elementor-Panel zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Elementor-Icon-Klasse.
	 */
	public function get_icon(): string {
		return 'eicon-post-list';
	}
}

/**
 * Class DHPS_Elementor_Widget_LXMIO
 *
 * Elementor-Widget fuer den Service MI-Online Recht.
 *
 * @since   0.7.0
 * @package Deubner Homepage-Service
 */
class DHPS_Elementor_Widget_LXMIO extends DHPS_Elementor_Widget_Base {

	/**
	 * Gibt den Service-Key zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Service-Key.
	 */
	protected function get_service_key(): string {
		return 'lxmio';
	}

	/**
	 * Gibt das Icon fuer das Elementor-Panel zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Elementor-Icon-Klasse.
	 */
	public function get_icon(): string {
		return 'eicon-post-list';
	}
}

/**
 * Class DHPS_Elementor_Widget_MMB
 *
 * Elementor-Widget fuer den Service Merkblaetter.
 *
 * @since   0.7.0
 * @package Deubner Homepage-Service
 */
class DHPS_Elementor_Widget_MMB extends DHPS_Elementor_Widget_Base {

	/**
	 * Gibt den Service-Key zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Service-Key.
	 */
	protected function get_service_key(): string {
		return 'mmb';
	}

	/**
	 * Gibt das Icon fuer das Elementor-Panel zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Elementor-Icon-Klasse.
	 */
	public function get_icon(): string {
		return 'eicon-document-file';
	}
}

/**
 * Class DHPS_Elementor_Widget_MIL
 *
 * Elementor-Widget fuer den Service Infografiken.
 *
 * @since   0.7.0
 * @package Deubner Homepage-Service
 */
class DHPS_Elementor_Widget_MIL extends DHPS_Elementor_Widget_Base {

	/**
	 * Gibt den Service-Key zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Service-Key.
	 */
	protected function get_service_key(): string {
		return 'mil';
	}

	/**
	 * Gibt das Icon fuer das Elementor-Panel zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Elementor-Icon-Klasse.
	 */
	public function get_icon(): string {
		return 'eicon-image';
	}
}

/**
 * Class DHPS_Elementor_Widget_TP
 *
 * Elementor-Widget fuer den Service TaxPlain Videos.
 *
 * @since   0.7.0
 * @package Deubner Homepage-Service
 */
class DHPS_Elementor_Widget_TP extends DHPS_Elementor_Widget_Base {

	/**
	 * Gibt den Service-Key zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Service-Key.
	 */
	protected function get_service_key(): string {
		return 'tp';
	}

	/**
	 * Gibt das Icon fuer das Elementor-Panel zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Elementor-Icon-Klasse.
	 */
	public function get_icon(): string {
		return 'eicon-play';
	}
}

/**
 * Class DHPS_Elementor_Widget_TPT
 *
 * Elementor-Widget fuer den Service TaxPlain Teaser.
 *
 * @since   0.7.0
 * @package Deubner Homepage-Service
 */
class DHPS_Elementor_Widget_TPT extends DHPS_Elementor_Widget_Base {

	/**
	 * Gibt den Service-Key zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Service-Key.
	 */
	protected function get_service_key(): string {
		return 'tpt';
	}

	/**
	 * Gibt das Icon fuer das Elementor-Panel zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Elementor-Icon-Klasse.
	 */
	public function get_icon(): string {
		return 'eicon-featured-image';
	}
}

/**
 * Class DHPS_Elementor_Widget_TC
 *
 * Elementor-Widget fuer den Service Tax-Rechner.
 *
 * @since   0.7.0
 * @package Deubner Homepage-Service
 */
class DHPS_Elementor_Widget_TC extends DHPS_Elementor_Widget_Base {

	/**
	 * Gibt den Service-Key zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Service-Key.
	 */
	protected function get_service_key(): string {
		return 'tc';
	}

	/**
	 * Gibt das Icon fuer das Elementor-Panel zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Elementor-Icon-Klasse.
	 */
	public function get_icon(): string {
		return 'eicon-number-field';
	}
}

/**
 * Class DHPS_Elementor_Widget_MAES
 *
 * Elementor-Widget fuer den Service Meine Aerzteseite.
 *
 * @since   0.7.0
 * @package Deubner Homepage-Service
 */
class DHPS_Elementor_Widget_MAES extends DHPS_Elementor_Widget_Base {

	/**
	 * Gibt den Service-Key zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Service-Key.
	 */
	protected function get_service_key(): string {
		return 'maes';
	}

	/**
	 * Gibt das Icon fuer das Elementor-Panel zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Elementor-Icon-Klasse.
	 */
	public function get_icon(): string {
		return 'eicon-person';
	}
}

/**
 * Class DHPS_Elementor_Widget_LP
 *
 * Elementor-Widget fuer den Service Lexplain.
 *
 * @since   0.7.0
 * @package Deubner Homepage-Service
 */
class DHPS_Elementor_Widget_LP extends DHPS_Elementor_Widget_Base {

	/**
	 * Gibt den Service-Key zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Service-Key.
	 */
	protected function get_service_key(): string {
		return 'lp';
	}

	/**
	 * Gibt das Icon fuer das Elementor-Panel zurueck.
	 *
	 * @since 0.7.0
	 *
	 * @return string Elementor-Icon-Klasse.
	 */
	public function get_icon(): string {
		return 'eicon-play';
	}
}
