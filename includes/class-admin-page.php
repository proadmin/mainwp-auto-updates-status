<?php
/**
 * Extension page: full run history, run details and settings.
 *
 * @package MainWP_Auto_Updates_Status
 */

namespace ProAdmin\MainWP_AUS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page class.
 */
class Admin_Page {

	const PER_PAGE = 25;

	/**
	 * Singleton instance.
	 *
	 * @var Admin_Page|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Admin_Page
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_post_mainwp_aus_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * The page slug MainWP builds from the plugin folder name.
	 *
	 * @return string
	 */
	public static function page_slug() {
		$dir = dirname( plugin_basename( MAINWP_AUS_FILE ) );
		return 'Extensions-' . str_replace( ' ', '-', ucwords( str_replace( '-', ' ', $dir ) ) );
	}

	/**
	 * Page URL.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::page_slug() );
	}

	/**
	 * Render the page (MainWP extension callback).
	 */
	public function render() {
		do_action( 'mainwp_pageheader_extensions', MAINWP_AUS_FILE );

		if ( ! Widget::can_view() ) {
			echo '<div class="ui segment"><div class="ui message">' . esc_html__( 'You do not have permission to view auto update history.', 'mainwp-auto-updates-status' ) . '</div></div>';
			do_action( 'mainwp_pagefooter_extensions', MAINWP_AUS_FILE );
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view parameters.
		$run_start = isset( $_GET['run'] ) ? absint( $_GET['run'] ) : 0;
		$paged     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$saved     = isset( $_GET['aus_saved'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		?>
		<div class="ui segment mainwp-aus-page">
			<?php if ( $saved ) : ?>
				<div class="ui green message"><?php esc_html_e( 'Settings saved.', 'mainwp-auto-updates-status' ); ?></div>
			<?php endif; ?>
			<?php
			if ( $run_start ) {
				$this->render_run( $run_start );
			} else {
				$this->render_overview( $paged );
			}
			?>
		</div>
		<?php
		do_action( 'mainwp_pagefooter_extensions', MAINWP_AUS_FILE );
	}

	/**
	 * Run list, failures and settings.
	 *
	 * @param int $paged Current page.
	 */
	private function render_overview( $paged ) {
		$total = DB::instance()->count_runs();
		$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$paged = min( $paged, $pages );
		$runs  = DB::instance()->get_runs( self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE );
		$fails = DB::instance()->get_recent_failures( 50 );
		$sites = DB::instance()->get_sites( wp_list_pluck( $fails, 'site_id' ) );
		?>
		<h2 class="ui header">
			<?php esc_html_e( 'Auto Update Run History', 'mainwp-auto-updates-status' ); ?>
			<div class="sub header"><?php esc_html_e( 'Every Advanced Automatic Updates run recorded on this Dashboard. Select a run to see each update.', 'mainwp-auto-updates-status' ); ?></div>
		</h2>
		<?php Widget::render_runs_table( $runs ); ?>
		<?php if ( $pages > 1 ) : ?>
			<div class="ui mini pagination menu">
				<?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
					<a class="item <?php echo $i === $paged ? 'active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'paged', $i, self::url() ) ); ?>"><?php echo esc_html( (string) $i ); ?></a>
				<?php endfor; ?>
			</div>
		<?php endif; ?>

		<div class="ui hidden divider"></div>
		<h3 class="ui header"><?php esc_html_e( 'Recent Failures', 'mainwp-auto-updates-status' ); ?></h3>
		<?php Widget::render_items_table( $fails, $sites, __( 'No failed automatic updates recorded.', 'mainwp-auto-updates-status' ), true ); ?>

		<div class="ui hidden divider"></div>
		<?php $this->render_settings(); ?>
		<?php
	}

	/**
	 * One run's details.
	 *
	 * @param int $run_start Run start.
	 */
	private function render_run( $run_start ) {
		$run = DB::instance()->get_run( $run_start );
		?>
		<a href="<?php echo esc_url( self::url() ); ?>" class="ui mini basic button"><i class="arrow left icon"></i><?php esc_html_e( 'All runs', 'mainwp-auto-updates-status' ); ?></a>
		<?php
		if ( ! $run ) {
			echo '<div class="ui message">' . esc_html__( 'That run was not found. It may have been removed by the retention setting.', 'mainwp-auto-updates-status' ) . '</div>';
			return;
		}
		$items = DB::instance()->get_items( $run_start );
		$sites = DB::instance()->get_sites( wp_list_pluck( $items, 'site_id' ) );
		$badge = Status::run_badge( $run );
		?>
		<h2 class="ui header">
			<?php
			/* translators: %s: run start time */
			echo esc_html( sprintf( __( 'Run started %s', 'mainwp-auto-updates-status' ), Status::format( $run->run_start ) ) );
			?>
			<span class="ui small <?php echo esc_attr( $badge[1] ); ?> label"><?php echo esc_html( $badge[0] ); ?></span>
		</h2>
		<div class="ui mini statistics">
			<div class="statistic"><div class="value"><?php echo esc_html( (string) $run->updated ); ?></div><div class="label"><?php esc_html_e( 'Updated', 'mainwp-auto-updates-status' ); ?></div></div>
			<div class="<?php echo $run->failed ? 'red ' : ''; ?>statistic"><div class="value"><?php echo esc_html( (string) $run->failed ); ?></div><div class="label"><?php esc_html_e( 'Failed', 'mainwp-auto-updates-status' ); ?></div></div>
			<div class="statistic"><div class="value"><?php echo esc_html( (string) $run->sites_updated ); ?></div><div class="label"><?php esc_html_e( 'Sites Changed', 'mainwp-auto-updates-status' ); ?></div></div>
			<div class="statistic"><div class="value"><?php echo esc_html( Status::duration( (int) $run->run_start, (int) $run->run_end ) ); ?></div><div class="label"><?php esc_html_e( 'Duration', 'mainwp-auto-updates-status' ); ?></div></div>
		</div>
		<div class="ui hidden divider"></div>
		<?php
		Widget::render_items_table( $items, $sites, __( 'Nothing was updated in this run.', 'mainwp-auto-updates-status' ) );
	}

	/**
	 * Settings form.
	 */
	private function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$days = (int) get_option( 'mainwp_aus_retention_days', 90 );
		?>
		<h3 class="ui header"><?php esc_html_e( 'Settings', 'mainwp-auto-updates-status' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ui form">
			<input type="hidden" name="action" value="mainwp_aus_save_settings">
			<?php wp_nonce_field( 'mainwp_aus_save_settings' ); ?>
			<div class="ui grid field">
				<label class="six wide column middle aligned" for="mainwp_aus_retention_days"><?php esc_html_e( 'Keep history for (days)', 'mainwp-auto-updates-status' ); ?></label>
				<div class="ten wide column">
					<input type="number" min="0" max="3650" id="mainwp_aus_retention_days" name="mainwp_aus_retention_days" value="<?php echo esc_attr( (string) $days ); ?>">
					<div class="ui tiny grey text"><?php esc_html_e( '0 keeps history forever. Older runs are removed after each run finishes.', 'mainwp-auto-updates-status' ); ?></div>
				</div>
			</div>
			<button type="submit" class="ui green button"><?php esc_html_e( 'Save Settings', 'mainwp-auto-updates-status' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Save settings.
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'mainwp-auto-updates-status' ) );
		}
		check_admin_referer( 'mainwp_aus_save_settings' );
		$days = isset( $_POST['mainwp_aus_retention_days'] ) ? absint( $_POST['mainwp_aus_retention_days'] ) : 90;
		update_option( 'mainwp_aus_retention_days', min( 3650, $days ), false );
		DB::instance()->prune( $days );
		wp_safe_redirect( add_query_arg( 'aus_saved', 1, self::url() ) );
		exit;
	}
}
