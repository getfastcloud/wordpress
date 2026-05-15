<?php
/**
 * Plugin settings data class.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

namespace FastCloud\WordPress;

/**
 * Wraps the FastCloudWP settings array stored in WordPress options.
 */
class Settings {

	/**
	 * Constructs the settings object.
	 *
	 * @param array $settings Associative array of plugin settings.
	 */
	public function __construct( protected array $settings ) {
		$this->settings = array_merge(
			array(
				'enabled'         => true,
				'autosync'        => false,
				'delete_media'    => false,
				'remove_original' => false,
			),
			$this->settings
		);
	}

	/**
	 * Determines whether the plugin is enabled and a bucket is configured.
	 */
	public function enabled(): bool {
		$bucket = get_option( 'fastcloudwp_bucket_name' );

		return (bool) ( $bucket && ( $this->settings['enabled'] ?? true ) );
	}

	/**
	 * Determines whether local media deletion after offloading is enabled.
	 */
	public function delete_media(): bool {
		if ( ! $this->enabled() ) {
			return false;
		}

		return (bool) ( $this->settings['delete_media'] ?? false );
	}

	/**
	 *  Determines whether original media deletion after offloading is enabled.
	 */
	public function remove_original(): bool {
		if ( ! $this->enabled() ) {
			return false;
		}

		return $this->settings['remove_original'] ?? false;
	}

	/**
	 * Determines whether media should be offloaded automatically after upload.
	 */
	public function autosync(): bool {
		if ( ! $this->enabled() ) {
			return false;
		}

		return (bool) ( $this->settings['autosync'] ?? false );
	}

	/**
	 * Returns the settings with the proper casting.
	 */
	public function to_array(): array {
		return array(
			'enabled'         => $this->enabled(),
			'autosync'        => $this->autosync(),
			'delete_media'    => $this->delete_media(),
			'remove_original' => $this->remove_original(),
		);
	}

	/**
	 * Merges partial settings data onto the current settings.
	 *
	 * @param array $data The settings to update.
	 */
	public function update( array $data ): void {
		$this->settings = array_merge( $this->settings, $data );
	}

	/**
	 * Resets settings to a disabled state.
	 */
	public function disable(): void {
		$this->settings['enabled']         = false;
		$this->settings['autosync']        = false;
		$this->settings['delete_media']    = false;
		$this->settings['remove_original'] = false;
	}

	/**
	 * Persists the current settings to the database.
	 */
	public function save(): void {
		update_option( 'fastcloudwp_settings', $this->to_array() );
	}
}
