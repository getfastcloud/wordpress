<?php
/**
 * The review request.
 *
 * @package FastCloudWP
 */

declare( strict_types=1 );

namespace FastCloud\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asks administrators for a WordPress.org review: at once when connected, otherwise after 2 days, then 14 days after "Maybe later".
 */
class Review_Notice {

	public const META   = 'fastcloudwp_review';
	public const DONE   = 'done';
	public const DELAY  = 14 * DAY_IN_SECONDS;
	public const WAIT   = 2 * DAY_IN_SECONDS;
	public const ACTION = 'fastcloudwp_review';
	public const URL    = 'https://wordpress.org/support/plugin/fastcloud-offload-media/reviews/#new-post';

	/**
	 * Registers the notice and the handler of its links.
	 */
	public function register_hooks(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Renders the review request, when due on this screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) || ! $this->due() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = null !== $screen ? $screen->id : '';

		if ( ! in_array( $id, array( 'dashboard', 'plugins', 'toplevel_page_fastcloud-offload-media' ), true ) ) {
			return;
		}

		?>
		<div class="notice notice-info fastcloudwp-notice-review">
			<p><?php esc_html_e( 'Is FastCloudWP useful? A review on WordPress.org helps others find it.', 'fastcloud-offload-media' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $this->choice_url( 'review' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Leave a review', 'fastcloud-offload-media' ); ?></a>
				<a href="<?php echo esc_url( $this->choice_url( 'later' ) ); ?>" style="margin-left:.6em"><?php esc_html_e( 'Maybe later', 'fastcloud-offload-media' ); ?></a>
				<a href="<?php echo esc_url( $this->choice_url( 'done' ) ); ?>" style="margin-left:.6em"><?php esc_html_e( 'I already did', 'fastcloud-offload-media' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Records the choice made on the review request and follows it.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'fastcloud-offload-media' ) );
		}

		check_admin_referer( self::ACTION );

		$choice = isset( $_GET['choice'] ) ? sanitize_key( wp_unslash( $_GET['choice'] ) ) : '';
		$user   = get_current_user_id();

		if ( 'later' === $choice ) {
			update_user_meta( $user, self::META, (string) ( time() + self::DELAY ) );
			$this->redirect_back();
		}

		update_user_meta( $user, self::META, self::DONE );

		if ( 'review' === $choice ) {
			add_filter( 'allowed_redirect_hosts', array( $this, 'allow_wordpress_org' ) );
			wp_safe_redirect( self::URL );
			exit;
		}

		$this->redirect_back();
	}

	/**
	 * Adds wordpress.org to the hosts a safe redirect may reach.
	 *
	 * @param array $hosts The allowed hosts.
	 */
	public function allow_wordpress_org( array $hosts ): array {
		$hosts[] = 'wordpress.org';

		return $hosts;
	}

	/**
	 * Determines whether the review request is due for the current user.
	 */
	protected function due(): bool {
		$user  = get_current_user_id();
		$state = (string) get_user_meta( $user, self::META, true );

		if ( self::DONE === $state ) {
			return false;
		}

		if ( '' === $state ) {
			if ( get_option( 'fastcloudwp_website_uuid' ) ) {
				return true;
			}

			update_user_meta( $user, self::META, (string) ( time() + self::WAIT ) );

			return false;
		}

		return time() >= (int) $state;
	}

	/**
	 * Builds the nonce-protected link that records one choice.
	 *
	 * @param string $choice The choice the link records.
	 */
	protected function choice_url( string $choice ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'choice' => $choice,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION
		);
	}

	/**
	 * Sends the user back where the choice was made, or to the dashboard.
	 */
	protected function redirect_back(): void {
		$referer = wp_get_referer();

		wp_safe_redirect( false !== $referer ? $referer : admin_url() );
		exit;
	}
}
