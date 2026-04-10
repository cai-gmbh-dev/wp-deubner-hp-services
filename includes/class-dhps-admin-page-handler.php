<?php
/**
 * Admin-Page-Handler fuer den Deubner Homepage Service.
 *
 * Verarbeitet das Speichern von Formular-Daten und das Laden
 * von Option-Werten fuer die Admin-Seiten. Trennt die Datenschicht
 * sauber von der Darstellungsschicht (Templates).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Admin
 * @since      0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DHPS_Admin_Page_Handler
 *
 * Kapselt die gesamte Formularverarbeitung (Nonce-Pruefung, Sanitisierung,
 * Speicherung) und das Laden von Option-Werten fuer die Admin-Templates.
 *
 * @since   0.4.0
 * @package Deubner Homepage-Service
 */
class DHPS_Admin_Page_Handler {

    /**
     * Speichert die Formular-Daten einer Standard-Service-Konfigurationsseite.
     *
     * Prueft den Nonce, liest die Service-Definition aus der Registry
     * und speichert alle admin_fields via sanitize_text_field + wp_unslash + update_option.
     *
     * @since 0.4.0
     *
     * @param string $page_slug Service-Slug (z.B. 'mmb', 'mil', 'tc').
     *
     * @return bool True wenn die Daten erfolgreich gespeichert wurden, sonst false.
     */
    public function save_settings( string $page_slug ): bool {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        if ( ! isset( $_POST['submit'] ) ) {
            return false;
        }

        if ( ! $this->verify_nonce( 'dhps_nonce' ) ) {
            return false;
        }

        $service = DHPS_Service_Registry::get_service( $page_slug );

        if ( null === $service || empty( $service['admin_fields'] ) ) {
            return false;
        }

        foreach ( $service['admin_fields'] as $field ) {
            $field_name = $field['field_name'];
            $option_key = $field['option_key'];
            $value      = isset( $_POST[ $field_name ] )
                ? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) )
                : '';

            update_option( $option_key, $value );
        }

        return true;
    }

    /**
     * Speichert die Formular-Daten eines Sibling-Service.
     *
     * Wird fuer Services verwendet, die sich eine Admin-Seite teilen
     * (z.B. 'tpt' auf der 'tp'-Seite). Jeder Sibling hat sein eigenes
     * Nonce-Feld und seine eigenen admin_fields.
     *
     * @since 0.4.0
     *
     * @param string $service_slug Shortcode-Name des Sibling-Service (z.B. 'tpt').
     * @param string $nonce_field  Name des Nonce-Feldes im POST-Request.
     *
     * @return bool True bei erfolgreichem Speichern, sonst false.
     */
    public function save_sibling_form( string $service_slug, string $nonce_field ): bool {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        if ( ! $this->verify_nonce( $nonce_field ) ) {
            return false;
        }

        $service = DHPS_Service_Registry::get_service( $service_slug );

        if ( null === $service || empty( $service['admin_fields'] ) ) {
            return false;
        }

        foreach ( $service['admin_fields'] as $field ) {
            $field_name = $field['field_name'];
            $option_key = $field['option_key'];
            $value      = isset( $_POST[ $field_name ] )
                ? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) )
                : '';

            update_option( $option_key, $value );
        }

        return true;
    }

    /**
     * Speichert ein einzelnes MIO-Formular (Steuerrecht oder Recht).
     *
     * Die MI-Online-Seite hat zwei eigenstaendige Formulare mit jeweils
     * eigenem Nonce. Diese Methode verarbeitet eines davon.
     *
     * @since 0.4.0
     *
     * @param string $form_key Formular-Key ('mio' oder 'lxmio').
     *
     * @return bool True bei erfolgreichem Speichern, sonst false.
     */
    public function save_mio_form( string $form_key ): bool {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        $service = DHPS_Service_Registry::get_service( $form_key );

        if ( null === $service || empty( $service['admin_fields'] ) ) {
            return false;
        }

        // Nonce-Feld bestimmen.
        $nonce_field = 'mio' === $form_key ? 'dhps_nonce' : 'dhps_lxmio_nonce';

        if ( ! $this->verify_nonce( $nonce_field ) ) {
            return false;
        }

        foreach ( $service['admin_fields'] as $field ) {
            $field_name = $field['field_name'];
            $option_key = $field['option_key'];
            $value      = isset( $_POST[ $field_name ] )
                ? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) )
                : '';

            update_option( $option_key, $value );
        }

        return true;
    }

    /**
     * Laedt die aktuellen Option-Werte fuer eine Standard-Service-Seite.
     *
     * Gibt ein assoziatives Array zurueck, das von field_name auf den
     * aktuellen Wert in der wp_options-Tabelle mappt.
     *
     * @since 0.4.0
     *
     * @param string $page_slug Service-Slug (z.B. 'mmb', 'mil', 'tc').
     *
     * @return array Assoziatives Array (field_name => option_value).
     */
    public function get_page_data( string $page_slug ): array {
        $service = DHPS_Service_Registry::get_service( $page_slug );
        $data    = array();

        if ( null === $service || empty( $service['admin_fields'] ) ) {
            return $data;
        }

        foreach ( $service['admin_fields'] as $field ) {
            $data[ $field['field_name'] ] = get_option( $field['option_key'], '' );
        }

        return $data;
    }

    /**
     * Laedt die aktuellen Option-Werte fuer ein MIO-Formular.
     *
     * @since 0.4.0
     *
     * @param string $form_key Formular-Key ('mio' oder 'lxmio').
     *
     * @return array Assoziatives Array (field_name => option_value).
     */
    public function get_mio_form_data( string $form_key ): array {
        $service = DHPS_Service_Registry::get_service( $form_key );
        $data    = array();

        if ( null === $service || empty( $service['admin_fields'] ) ) {
            return $data;
        }

        foreach ( $service['admin_fields'] as $field ) {
            $data[ $field['field_name'] ] = get_option( $field['option_key'], '' );
        }

        return $data;
    }

    /**
     * Verifiziert den Nonce-Wert eines Admin-Formulars.
     *
     * @since 0.4.0
     *
     * @param string $nonce_field Name des Nonce-Feldes im POST-Request.
     *
     * @return bool True wenn der Nonce gueltig ist, sonst false.
     */
    private function verify_nonce( string $nonce_field = 'dhps_nonce' ): bool {
        if ( ! isset( $_POST[ $nonce_field ] ) ) {
            return false;
        }

        return (bool) wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ),
            DEUBNER_HP_SERVICES_NONCE_ACTION
        );
    }
}
