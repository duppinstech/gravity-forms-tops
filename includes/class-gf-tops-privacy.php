<?php
/**
 * WordPress privacy tools: suggested privacy-policy text and personal-data export / erase for the request log.
 *
 * @package GF_Tops
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GF_Tops_Privacy
 */
class GF_Tops_Privacy {

	const ROWS_PER_PAGE = 50;

	/**
	 * Register hooks.
	 */
	public static function register_hooks() {
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
		// Priority 5: run before Gravity Forms' own eraser, which may delete the entries used to find log rows.
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ), 5 );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ), 5 );
	}

	/**
	 * Suggested text for Settings → Privacy → Policy Guide.
	 */
	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content( __( 'Gravity Forms TOPS / TowX Integration', 'gravity-forms-tops' ), wp_kses_post( wpautop( self::policy_text(), false ) ) );
	}

	/**
	 * @return string
	 */
	public static function policy_text() {
		$paragraphs = array(
			'<strong>' . __( 'Suggested text:', 'gravity-forms-tops' ) . '</strong> ' . __( 'When you submit a tow or roadside request on this site, the details you enter (such as your name, phone number, pickup location, destination, vehicle year, make, model and color, license plate, and notes) are sent to our dispatch system provider, TowXchange (TowX / TOPS), to create the service call.', 'gravity-forms-tops' ),
			__( 'A copy of each request sent to the dispatch system, and its response, is kept in this site\'s database so that we can confirm, troubleshoot, and resend requests. These records are kept until they are erased on request or the plugin is deleted. Dispatch login credentials are removed from these records.', 'gravity-forms-tops' ),
			__( 'If a request cannot be delivered to the dispatch system, the request details may be emailed to our staff so that we can follow up with you. Those emails are kept in our mailboxes and are not removed by an erasure request to this site.', 'gravity-forms-tops' ),
			__( 'You can ask us for a copy of this information, or ask us to erase it, using the contact details on this site.', 'gravity-forms-tops' ),
			'<em>' . __( 'For site owners: this plugin also checks GitHub (github.com) for plugin updates. That check does not include visitor data.', 'gravity-forms-tops' ) . '</em>',
		);
		return implode( "\n\n", $paragraphs );
	}

	/**
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['gravity-forms-tops'] = array(
			'exporter_friendly_name' => __( 'TOPS / TowX request log', 'gravity-forms-tops' ),
			'callback'               => array( __CLASS__, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['gravity-forms-tops'] = array(
			'eraser_friendly_name' => __( 'TOPS / TowX request log', 'gravity-forms-tops' ),
			'callback'             => array( __CLASS__, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Gravity Forms entry IDs that contain this email address in any field.
	 *
	 * @param string $email Email address.
	 * @return int[]
	 */
	public static function find_entry_ids( $email ) {
		if ( ! class_exists( 'GFAPI' ) || ! is_email( $email ) ) {
			return array();
		}
		$ids = array();
		foreach ( array( 'active', 'spam', 'trash' ) as $status ) {
			$page = 0;
			do {
				$entries = GFAPI::get_entries(
					0,
					array(
						'status'        => $status,
						'field_filters' => array(
							array( 'value' => $email ),
						),
					),
					null,
					array(
						'offset'    => $page * 200,
						'page_size' => 200,
					)
				);
				if ( is_wp_error( $entries ) || ! is_array( $entries ) ) {
					break;
				}
				foreach ( $entries as $entry ) {
					$ids[] = (int) rgar( $entry, 'id' );
				}
				$batch = count( $entries );
				++$page;
			} while ( 200 === $batch && $page < 50 );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Personal-data exporter callback.
	 *
	 * @param string $email Email address.
	 * @param int    $page  1-based page.
	 * @return array
	 */
	public static function export_personal_data( $email, $page = 1 ) {
		$page = max( 1, (int) $page );
		$rows = GF_Tops_Request_Log::find_for_person( self::find_entry_ids( $email ), $email, self::ROWS_PER_PAGE, ( $page - 1 ) * self::ROWS_PER_PAGE );

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'group_id'    => 'gf-tops-request-log',
				'group_label' => __( 'Tow request log', 'gravity-forms-tops' ),
				'item_id'     => 'gf-tops-log-' . (int) $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Date (UTC)', 'gravity-forms-tops' ),
						'value' => (string) $row->created_at,
					),
					array(
						'name'  => __( 'Form entry', 'gravity-forms-tops' ),
						'value' => (string) (int) $row->entry_id,
					),
					array(
						'name'  => __( 'Status', 'gravity-forms-tops' ),
						'value' => (string) $row->status,
					),
					array(
						'name'  => __( 'Call ID', 'gravity-forms-tops' ),
						'value' => (string) $row->call_key,
					),
					array(
						'name'  => __( 'Request sent to dispatch (credentials removed)', 'gravity-forms-tops' ),
						'value' => (string) $row->request_xml_redacted,
					),
					array(
						'name'  => __( 'Dispatch response', 'gravity-forms-tops' ),
						'value' => (string) $row->response_raw,
					),
				),
			);
		}

		return array(
			'data' => $items,
			'done' => count( $rows ) < self::ROWS_PER_PAGE,
		);
	}

	/**
	 * Personal-data eraser callback: deletes this person's request-log rows.
	 *
	 * @param string $email Email address.
	 * @param int    $page  1-based page (all matching rows are removed on the first call).
	 * @return array
	 */
	public static function erase_personal_data( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature required by WordPress erasers.
		$removed = GF_Tops_Request_Log::delete_for_person( self::find_entry_ids( $email ), $email );

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(
				__( 'TOPS / TowX request log: rows are found by this email address in Gravity Forms entries or in the stored request. Requests submitted without this email address cannot be matched automatically; search Forms → TOPS log by name or phone and remove them if needed. Failure notification emails already sent are not affected.', 'gravity-forms-tops' ),
			),
			'done'           => true,
		);
	}
}
