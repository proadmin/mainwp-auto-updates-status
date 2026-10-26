<?php
/**
 * Operations dashboard widget.
 *
 * @package   MainWP_Auto_Updates_Status
 * @author    ProAdmin <https://proadminwp.net/>
 * @copyright Copyright (c) 2026 ProAdmin
 * @license   GPL-2.0-or-later
 */

namespace ProAdmin\MainWP_AUS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget class.
 */
class Widget {

	/**
	 * Widget id passed to MainWP. MainWP prefixes it with "advanced-".
	 */
	const WIDGET_ID = 'auto-updates-status';

	/**
	 * Singleton instance.
	 *
	 * @var Widget|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Widget
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
		add_filter( 'mainwp_getmetaboxes', array( $this, 'add_widget' ), 10, 2 );
		add_filter( 'mainwp_widgets_screen_options', array( $this, 'add_screen_option' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_mainwp_aus_widget_refresh', array( $this, 'ajax_refresh' ) );
	}

	/**
	 * Whether the current user may see auto update status.
	 *
	 * @return bool
	 */
	public static function can_view() {
		/**
		 * Filters the WordPress capability required to view the Auto Updates widget and history.
		 *
		 * @param string $capability Capability. Default 'manage_options'.
		 */
		$capability = (string) apply_filters( 'mainwp_aus_capability', 'manage_options' );

		// MainWP's own permission check returns true for everyone unless a team permissions add-on is active,
		// so a WordPress capability is always required as well.
		$allowed = current_user_can( $capability );
		if ( $allowed && function_exists( 'mainwp_current_user_can' ) ) {
			$allowed = (bool) \mainwp_current_user_can( 'dashboard', 'access_global_dashboard' );
		}
		/**
		 * Filters whether the current user can view the Auto Updates widget and history.
		 *
		 * @param bool $allowed Allowed.
		 */
		return (bool) apply_filters( 'mainwp_aus_can_view', $allowed );
	}

	/**
	 * Add the widget to the Operations (global overview) page only.
	 *
	 * @param array    $widgets          Widgets.
	 * @param int|null $dashboard_siteid Set on individual site overview pages.
	 * @return array
	 */
	public function add_widget( $widgets, $dashboard_siteid = null ) {
		if ( ! empty( $dashboard_siteid ) || ! self::can_view() ) {
			return $widgets;
		}
		if ( ! is_array( $widgets ) ) {
			$widgets = array();
		}
		$widgets[] = array(
			'plugin'   => MAINWP_AUS_FILE,
			'id'       => self::WIDGET_ID,
			'callback' => array( $this, 'render' ),
			'layout'   => array( -1, -1, 6, 40 ),
		);
		return $widgets;
	}

	/**
	 * Add a show/hide toggle to the Operations page settings.
	 *
	 * @param array $options Screen options.
	 * @return array
	 */
	public function add_screen_option( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options[ 'advanced-' . self::WIDGET_ID ] = __( 'Auto Updates Status', 'mainwp-auto-updates-status' );
		return $options;
	}

	/**
	 * Load assets on the Operations page and our extension page.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'toplevel_page_mainwp_tab' !== $hook && strtolower( Admin_Page::page_slug() ) !== $page ) {
			return;
		}
		wp_enqueue_style( 'mainwp-aus', MAINWP_AUS_URL . 'assets/css/mainwp-aus.css', array(), MAINWP_AUS_VERSION );
		wp_enqueue_script( 'mainwp-aus', MAINWP_AUS_URL . 'assets/js/mainwp-aus.js', array( 'jquery' ), MAINWP_AUS_VERSION, true );
		wp_localize_script(
			'mainwp-aus',
			'mainwpAus',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'mainwp_aus_refresh' ),
				'refreshInterval' => (int) apply_filters( 'mainwp_aus_refresh_interval', 60 ),
			)
		);
	}

	/**
	 * Render the widget (MainWP widget callback).
	 */
	public function render() {
		if ( ! self::can_view() ) {
			return;
		}
		?>
		<div class="mainwp-widget-header">
			<div class="ui grid">
				<div class="twelve wide column">
					<h2 class="ui header handle-drag">
						<?php echo esc_html( apply_filters( 'mainwp_aus_widget_title', __( 'Auto Updates', 'mainwp-auto-updates-status' ) ) ); ?>
						<div class="sub header"><?php esc_html_e( 'Advanced Automatic Updates status and history', 'mainwp-auto-updates-status' ); ?></div>
					</h2>
				</div>
				<div class="four wide column right aligned">
					<a href="#" class="mainwp-aus-refresh" data-tooltip="<?php esc_attr_e( 'Refresh', 'mainwp-auto-updates-status' ); ?>" data-position="left center" data-inverted=""><i class="redo alternate icon"></i></a>
					<div id="mainwp-aus-dropdown" class="ui dropdown top right tiny pointing not-auto-init mainwp-dropdown-tab">
						<i class="vertical ellipsis icon"></i>
						<div class="menu">
							<a class="item" data-tab="aus-status" data-value="aus-status" href="#"><?php esc_html_e( 'Status', 'mainwp-auto-updates-status' ); ?></a>
							<a class="item" data-tab="aus-last-run" data-value="aus-last-run" href="#"><?php esc_html_e( 'Latest Run', 'mainwp-auto-updates-status' ); ?></a>
							<a class="item" data-tab="aus-history" data-value="aus-history" href="#"><?php esc_html_e( 'Run History', 'mainwp-auto-updates-status' ); ?></a>
							<a class="item" data-tab="aus-failures" data-value="aus-failures" href="#"><?php esc_html_e( 'Recent Failures', 'mainwp-auto-updates-status' ); ?></a>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div id="mainwp-aus-widget-body">
			<?php $this->render_body(); ?>
		</div>
		<div class="ui two column grid mainwp-widget-footer">
			<div class="left aligned middle aligned column">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=Settings' ) ); ?>" class="ui mini basic button"><?php esc_html_e( 'Schedule Settings', 'mainwp-auto-updates-status' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=PluginsAutoUpdate' ) ); ?>" class="ui mini basic button"><?php esc_html_e( 'Trusted Plugins', 'mainwp-auto-updates-status' ); ?></a>
			</div>
			<div class="right aligned middle aligned column">
				<a href="<?php echo esc_url( Admin_Page::url() ); ?>" class="ui mini green basic button"><?php esc_html_e( 'Full History', 'mainwp-auto-updates-status' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the refreshable part of the widget.
	 */
	public function render_body() {
		Status::close_stale_runs();
		$runs   = DB::instance()->get_runs( 10 );
		$status = Status::get( $runs );
		$latest = $this->latest_detailed_run( $runs );
		$items  = $latest ? DB::instance()->get_items( $latest->run_start ) : array();
		$fails  = DB::instance()->get_recent_failures( 15 );

		$site_ids = array_merge( wp_list_pluck( $items, 'site_id' ), wp_list_pluck( $fails, 'site_id' ) );
		$sites    = DB::instance()->get_sites( $site_ids );
		?>
		<div class="mainwp-aus-body" data-running="<?php echo 'running' === $status['state'] ? '1' : '0'; ?>">
			<?php $this->render_cards( $status ); ?>
			<div class="mainwp-scrolly-overflow">
				<div class="ui tab" data-tab="aus-status"><?php $this->render_status_tab( $status ); ?></div>
				<div class="ui tab" data-tab="aus-last-run"><?php $this->render_items_tab( $latest, $items, $sites ); ?></div>
				<div class="ui tab" data-tab="aus-history"><?php self::render_runs_table( $runs, true ); ?></div>
				<div class="ui tab" data-tab="aus-failures"><?php $this->render_failures_tab( $fails, $sites ); ?></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Most recent run that has item details (or is still running).
	 *
	 * @param array $runs Newest runs.
	 * @return object|null
	 */
	private function latest_detailed_run( $runs ) {
		foreach ( $runs as $run ) {
			if ( ! empty( $run->details_recorded ) ) {
				return $run;
			}
		}
		return null;
	}

	/**
	 * Summary cards.
	 *
	 * @param array $status Status.
	 */
	private function render_cards( $status ) {
		$states = array(
			'running'   => array( __( 'Running', 'mainwp-auto-updates-status' ), 'sync alternate loading blue' ),
			'scheduled' => array( __( 'Scheduled', 'mainwp-auto-updates-status' ), 'clock outline green' ),
			'overdue'   => array( __( 'Overdue', 'mainwp-auto-updates-status' ), 'exclamation triangle orange' ),
			'disabled'  => array( __( 'Disabled', 'mainwp-auto-updates-status' ), 'pause circle outline grey' ),
		);
		$state  = $states[ $status['state'] ];
		$latest = $status['latest_run'];
		?>
		<div class="ui mainwp-cards small three cards mainwp-aus-cards">
			<div class="ui card">
				<div class="content">
					<div class="header"><span class="ui large text"><i class="<?php echo esc_attr( $state[1] ); ?> icon"></i> <?php echo esc_html( $state[0] ); ?></span></div>
					<div class="description">
						<strong><?php esc_html_e( 'Status', 'mainwp-auto-updates-status' ); ?></strong>
						<?php if ( 'running' === $status['state'] && $status['progress'] && $status['progress']['total'] > 0 ) : ?>
							<?php /* translators: 1: sites done, 2: sites total */ ?>
							<div class="ui small text"><?php echo esc_html( sprintf( __( '%1$d of %2$d sites processed', 'mainwp-auto-updates-status' ), $status['progress']['done'], $status['progress']['total'] ) ); ?></div>
						<?php elseif ( 'overdue' === $status['state'] ) : ?>
							<div class="ui small text"><?php esc_html_e( 'Scheduled time passed without a run. Check that cron is running.', 'mainwp-auto-updates-status' ); ?></div>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="ui card">
				<div class="content">
					<div class="header"><span class="ui large text"><i class="calendar alternate outline icon"></i> <?php echo esc_html( $status['next_run'] ? Status::relative( $status['next_run'] ) : '—' ); ?></span></div>
					<div class="description">
						<strong><?php esc_html_e( 'Next Run', 'mainwp-auto-updates-status' ); ?></strong>
						<div class="ui small text"><?php echo esc_html( $status['next_run'] ? Status::format( $status['next_run'] ) : __( 'Not scheduled', 'mainwp-auto-updates-status' ) ); ?></div>
					</div>
				</div>
			</div>
			<div class="ui card">
				<div class="content">
					<?php if ( $latest ) : ?>
						<?php $badge = Status::run_badge( $latest ); ?>
						<div class="header"><span class="ui large text"><i class="history icon"></i> <?php echo esc_html( Status::relative( $latest->run_start ) ); ?></span></div>
						<div class="description">
							<strong><?php esc_html_e( 'Last Run', 'mainwp-auto-updates-status' ); ?></strong>
							<span class="ui mini <?php echo esc_attr( $badge[1] ); ?> label"><?php echo esc_html( $badge[0] ); ?></span>
							<?php if ( ! empty( $latest->details_recorded ) ) : ?>
								<?php /* translators: 1: updated count, 2: failed count */ ?>
								<div class="ui small text"><?php echo esc_html( sprintf( __( '%1$d updated, %2$d failed', 'mainwp-auto-updates-status' ), $latest->updated, $latest->failed ) ); ?></div>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<div class="header"><span class="ui large text"><i class="history icon"></i> —</span></div>
						<div class="description"><strong><?php esc_html_e( 'Last Run', 'mainwp-auto-updates-status' ); ?></strong><div class="ui small text"><?php esc_html_e( 'No runs recorded yet', 'mainwp-auto-updates-status' ); ?></div></div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Status tab: schedule, enabled types, progress.
	 *
	 * @param array $status Status.
	 */
	private function render_status_tab( $status ) {
		$type_labels = array(
			'core'        => __( 'WordPress Core', 'mainwp-auto-updates-status' ),
			'plugin'      => __( 'Plugins', 'mainwp-auto-updates-status' ),
			'theme'       => __( 'Themes', 'mainwp-auto-updates-status' ),
			'translation' => __( 'Translations', 'mainwp-auto-updates-status' ),
		);
		?>
		<?php if ( 'running' === $status['state'] && $status['progress'] && $status['progress']['total'] > 0 ) : ?>
			<?php $pct = (int) floor( 100 * $status['progress']['done'] / max( 1, $status['progress']['total'] ) ); ?>
			<div class="ui small progress mainwp-aus-progress" data-percent="<?php echo esc_attr( $pct ); ?>">
				<div class="bar" style="width: <?php echo esc_attr( max( 2, $pct ) ); ?>%;"><div class="progress"><?php echo esc_html( $pct ); ?>%</div></div>
				<?php /* translators: %s: start time */ ?>
				<div class="label"><?php echo esc_html( sprintf( __( 'Run started %s', 'mainwp-auto-updates-status' ), Status::format( $status['run_start'] ) ) ); ?></div>
			</div>
		<?php endif; ?>
		<table class="ui very basic compact unstackable table mainwp-aus-definition">
			<tbody>
				<tr>
					<td class="five wide"><strong><?php esc_html_e( 'Schedule', 'mainwp-auto-updates-status' ); ?></strong></td>
					<td><?php echo esc_html( $status['schedule'] ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Update delay', 'mainwp-auto-updates-status' ); ?></strong></td>
					<td>
						<?php
						echo esc_html(
							$status['delay_days'] > 0
								/* translators: %d: number of days */
								? sprintf( _n( '%d day after release', '%d days after release', $status['delay_days'], 'mainwp-auto-updates-status' ), $status['delay_days'] )
								: __( 'No delay', 'mainwp-auto-updates-status' )
						);
						?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Update types', 'mainwp-auto-updates-status' ); ?></strong></td>
					<td>
						<?php foreach ( $type_labels as $key => $label ) : ?>
							<span class="ui mini <?php echo $status['types'][ $key ] ? 'green' : 'basic'; ?> label" data-tooltip="<?php echo esc_attr( $status['types'][ $key ] ? __( 'Install trusted updates', 'mainwp-auto-updates-status' ) : __( 'Disabled', 'mainwp-auto-updates-status' ) ); ?>" data-inverted=""><?php echo esc_html( $label ); ?></span>
						<?php endforeach; ?>
					</td>
				</tr>
				<?php if ( $status['trusted'] ) : ?>
				<tr>
					<td><strong><?php esc_html_e( 'Trusted items', 'mainwp-auto-updates-status' ); ?></strong></td>
					<td>
						<?php
						/* translators: 1: trusted plugins count, 2: trusted themes count */
						echo esc_html( sprintf( __( '%1$d plugins, %2$d themes', 'mainwp-auto-updates-status' ), $status['trusted']['plugins'], $status['trusted']['themes'] ) );
						?>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<td><strong><?php esc_html_e( 'Last started', 'mainwp-auto-updates-status' ); ?></strong></td>
					<td><?php echo esc_html( Status::format( $status['run_start'] ) ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php if ( ! $status['enabled'] ) : ?>
			<div class="ui small message">
				<?php esc_html_e( 'Advanced Automatic Updates are disabled for core, plugins and themes. Enable them in MainWP Settings to start scheduled runs.', 'mainwp-auto-updates-status' ); ?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Latest run items.
	 *
	 * @param object|null $run   Run.
	 * @param array       $items Items.
	 * @param array       $sites Sites lookup.
	 */
	private function render_items_tab( $run, $items, $sites ) {
		if ( ! $run ) {
			echo '<div class="ui small message">' . esc_html__( 'No run details recorded yet. Details appear after the next automatic update run.', 'mainwp-auto-updates-status' ) . '</div>';
			return;
		}
		?>
		<div class="ui small header">
			<?php
			/* translators: %s: run start time */
			echo esc_html( sprintf( __( 'Run started %s', 'mainwp-auto-updates-status' ), Status::format( $run->run_start ) ) );
			?>
		</div>
		<?php
		self::render_items_table( $items, $sites, __( 'Nothing was updated in this run.', 'mainwp-auto-updates-status' ) );
	}

	/**
	 * Recent failures.
	 *
	 * @param array $items Failed items.
	 * @param array $sites Sites lookup.
	 */
	private function render_failures_tab( $items, $sites ) {
		self::render_items_table( $items, $sites, __( 'No failed automatic updates recorded.', 'mainwp-auto-updates-status' ), true );
	}

	/**
	 * Items table shared by the widget and the extension page.
	 *
	 * @param array  $items     Items.
	 * @param array  $sites     Sites lookup.
	 * @param string $empty     Empty message.
	 * @param bool   $show_date Show the recorded date column.
	 */
	public static function render_items_table( $items, $sites, $empty, $show_date = false ) {
		if ( empty( $items ) ) {
			echo '<div class="ui small message">' . esc_html( $empty ) . '</div>';
			return;
		}
		?>
		<table class="ui very compact unstackable small table mainwp-aus-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Site', 'mainwp-auto-updates-status' ); ?></th>
					<th><?php esc_html_e( 'Item', 'mainwp-auto-updates-status' ); ?></th>
					<th><?php esc_html_e( 'Version', 'mainwp-auto-updates-status' ); ?></th>
					<th><?php esc_html_e( 'Result', 'mainwp-auto-updates-status' ); ?></th>
					<?php if ( $show_date ) : ?>
						<th><?php esc_html_e( 'When', 'mainwp-auto-updates-status' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<?php $site = isset( $sites[ $item->site_id ] ) ? $sites[ $item->site_id ] : null; ?>
					<tr>
						<td>
							<?php if ( $site ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=managesites&dashboard=' . (int) $item->site_id ) ); ?>"><?php echo esc_html( stripslashes( $site->name ) ); ?></a>
							<?php else : ?>
								<?php /* translators: %d: site id */ ?>
								<span class="ui grey text"><?php echo esc_html( sprintf( __( 'Deleted site #%d', 'mainwp-auto-updates-status' ), $item->site_id ) ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $item->name ); ?>
							<div class="ui tiny grey text"><?php echo esc_html( Status::type_label( $item->item_type ) ); ?></div>
						</td>
						<td class="mainwp-aus-version">
							<?php
							if ( '' !== $item->old_version || '' !== $item->new_version ) {
								echo esc_html( ( '' !== $item->old_version ? $item->old_version : '?' ) . ' → ' . ( '' !== $item->new_version ? $item->new_version : '?' ) );
							}
							?>
						</td>
						<td>
							<?php if ( $item->success ) : ?>
								<span class="ui mini green label"><?php esc_html_e( 'Updated', 'mainwp-auto-updates-status' ); ?></span>
							<?php elseif ( $item->rolled_back ) : ?>
								<span class="ui mini orange label" data-tooltip="<?php echo esc_attr( $item->error ); ?>" data-inverted="" data-position="left center"><?php esc_html_e( 'Rolled back', 'mainwp-auto-updates-status' ); ?></span>
							<?php else : ?>
								<span class="ui mini red label" <?php echo '' !== (string) $item->error ? 'data-tooltip="' . esc_attr( $item->error ) . '" data-inverted="" data-position="left center"' : ''; ?>><?php esc_html_e( 'Failed', 'mainwp-auto-updates-status' ); ?></span>
							<?php endif; ?>
						</td>
						<?php if ( $show_date ) : ?>
							<td><?php echo esc_html( Status::relative( $item->recorded ) ); ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Runs table shared by the widget and the extension page.
	 *
	 * @param array $runs    Runs.
	 * @param bool  $compact Compact widget version.
	 */
	public static function render_runs_table( $runs, $compact = false ) {
		if ( empty( $runs ) ) {
			echo '<div class="ui small message">' . esc_html__( 'No runs recorded yet.', 'mainwp-auto-updates-status' ) . '</div>';
			return;
		}
		?>
		<table class="ui very compact unstackable small table mainwp-aus-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Started', 'mainwp-auto-updates-status' ); ?></th>
					<?php if ( ! $compact ) : ?>
						<th><?php esc_html_e( 'Duration', 'mainwp-auto-updates-status' ); ?></th>
						<th><?php esc_html_e( 'Sites', 'mainwp-auto-updates-status' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( 'Updated', 'mainwp-auto-updates-status' ); ?></th>
					<th><?php esc_html_e( 'Failed', 'mainwp-auto-updates-status' ); ?></th>
					<th><?php esc_html_e( 'Result', 'mainwp-auto-updates-status' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $runs as $run ) : ?>
					<?php
					$badge   = Status::run_badge( $run );
					$details = ! empty( $run->details_recorded );
					?>
					<tr>
						<td>
							<?php if ( $details ) : ?>
								<a href="<?php echo esc_url( add_query_arg( 'run', (int) $run->run_start, Admin_Page::url() ) ); ?>"><?php echo esc_html( Status::format( $run->run_start ) ); ?></a>
							<?php else : ?>
								<?php echo esc_html( Status::format( $run->run_start ) ); ?>
							<?php endif; ?>
						</td>
						<?php if ( ! $compact ) : ?>
							<td><?php echo esc_html( Status::duration( (int) $run->run_start, (int) $run->run_end ) ); ?></td>
							<td>
								<?php
								if ( $run->sites_total > 0 ) {
									/* translators: 1: sites with updates, 2: sites in run */
									echo esc_html( sprintf( __( '%1$d of %2$d', 'mainwp-auto-updates-status' ), $run->sites_updated, $run->sites_total ) );
								} else {
									echo esc_html( $details ? (string) $run->sites_updated : '—' );
								}
								?>
							</td>
						<?php endif; ?>
						<td><?php echo esc_html( $details ? (string) $run->updated : '—' ); ?></td>
						<td><?php echo $details && $run->failed > 0 ? '<strong class="ui red text">' . esc_html( (string) $run->failed ) . '</strong>' : esc_html( $details ? '0' : '—' ); ?></td>
						<td><span class="ui mini <?php echo esc_attr( $badge[1] ); ?> label"><?php echo esc_html( $badge[0] ); ?></span></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * AJAX: return fresh widget body HTML.
	 */
	public function ajax_refresh() {
		check_ajax_referer( 'mainwp_aus_refresh', 'nonce' );
		if ( ! self::can_view() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to view this.', 'mainwp-auto-updates-status' ) ), 403 );
		}
		ob_start();
		$this->render_body();
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}
}
