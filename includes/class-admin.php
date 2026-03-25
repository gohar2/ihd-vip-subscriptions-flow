<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IHD_VIP_Admin {

    const OPTION_KEY = 'ihd_vip_scoped_users';
    const NONCE_ACTION = 'ihd_vip_admin_save';
    const PER_PAGE = 15;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_ihd_vip_search_users', array( $this, 'ajax_search_users' ) );
        add_action( 'wp_ajax_ihd_vip_audit_logs', array( $this, 'ajax_audit_logs' ) );
        add_action( 'wp_ajax_ihd_vip_toggle_scope_mode', array( $this, 'ajax_toggle_scope_mode' ) );
        add_action( 'admin_post_ihd_vip_save_settings', array( $this, 'save_settings' ) );
    }

    public function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            'IHD VIP Subscriptions',
            'IHD VIP Subs',
            'manage_woocommerce',
            'ihd-vip-subscriptions',
            array( $this, 'render_settings_page' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'woocommerce_page_ihd-vip-subscriptions' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC_VERSION );
        wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', array( 'jquery' ), WC_VERSION, true );

        wp_add_inline_script( 'select2', $this->get_inline_js() );
        wp_add_inline_style( 'select2', $this->get_inline_css() );
    }

    public function ajax_search_users() {
        check_ajax_referer( 'ihd_vip_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

        if ( strlen( $term ) < 2 ) {
            wp_send_json( array( 'results' => array() ) );
        }

        $users = get_users( array(
            'search'         => '*' . $term . '*',
            'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
            'number'         => 20,
            'fields'         => array( 'ID', 'user_email', 'display_name' ),
        ) );

        $results = array();
        foreach ( $users as $user ) {
            $results[] = array(
                'id'   => $user->ID,
                'text' => sprintf( '%s (%s) — #%d', $user->display_name, $user->user_email, $user->ID ),
            );
        }

        wp_send_json( array( 'results' => $results ) );
    }

    /**
     * AJAX handler: Toggle scope mode between development and production.
     */
    public function ajax_toggle_scope_mode() {
        check_ajax_referer( 'ihd_vip_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $new_mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';

        if ( ! in_array( $new_mode, array( 'development', 'production' ), true ) ) {
            wp_send_json_error( 'Invalid mode.' );
        }

        update_option( IHD_VIP_User_Scope_Gate::MODE_OPTION_KEY, $new_mode );

        wp_send_json_success( array(
            'mode'  => $new_mode,
            'label' => 'production' === $new_mode ? 'Production Mode' : 'Development Mode',
        ) );
    }

    public function ajax_audit_logs() {
        check_ajax_referer( 'ihd_vip_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ihd_vip_subscription_audit';

        $page       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $reason     = isset( $_GET['filter_reason'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_reason'] ) ) : '';
        $event_type = isset( $_GET['filter_event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_event_type'] ) ) : '';
        $user_id    = isset( $_GET['filter_user'] ) ? absint( $_GET['filter_user'] ) : 0;
        $date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
        $date_to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

        $where  = array( '1=1' );
        $params = array();

        if ( $reason !== '' ) {
            $where[]  = 'a.reason = %s';
            $params[] = $reason;
        }

        if ( $event_type !== '' ) {
            $where[]  = 'a.event_type = %s';
            $params[] = $event_type;
        }

        if ( $user_id > 0 ) {
            $where[]  = 'a.customer_id = %d';
            $params[] = $user_id;
        }

        if ( $date_from !== '' ) {
            $where[]  = 'a.created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        if ( $date_to !== '' ) {
            $where[]  = 'a.created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $where );

        // Count
        $count_sql = "SELECT COUNT(*) FROM {$table} a WHERE {$where_sql}";
        if ( ! empty( $params ) ) {
            $count_sql = $wpdb->prepare( $count_sql, $params );
        }
        $total = (int) $wpdb->get_var( $count_sql );

        $offset = ( $page - 1 ) * self::PER_PAGE;

        // Data — ordered by id DESC for deterministic ordering.
        $data_sql    = "SELECT a.* FROM {$table} a WHERE {$where_sql} ORDER BY a.id DESC LIMIT %d OFFSET %d";
        $data_params = array_merge( $params, array( self::PER_PAGE, $offset ) );
        $rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

        // Build HTML rows
        $html = '';
        if ( empty( $rows ) ) {
            $html = '<tr><td colspan="10" class="ihd-audit-empty">No audit log entries found.</td></tr>';
        } else {
            foreach ( $rows as $row ) {
                // Customer info.
                $customer_info = '—';
                $cid = absint( $row['customer_id'] ?? 0 );
                if ( $cid > 0 ) {
                    $u = get_userdata( $cid );
                    if ( $u ) {
                        $customer_info = '<strong>' . esc_html( $u->display_name ) . '</strong><br><span class="ihd-audit-email">' . esc_html( $u->user_email ) . '</span>';
                    }
                }

                // Event type badge.
                $event_type_val = esc_html( $row['event_type'] ?? '' );
                $event_badge_class = 'ihd-badge-no';
                switch ( $row['event_type'] ) {
                    case 'cancellation':
                        $event_badge_class = 'ihd-badge-danger';
                        break;
                    case 'payment_failure':
                        $event_badge_class = 'ihd-badge-warning';
                        break;
                    case 'expiration':
                        $event_badge_class = 'ihd-badge-admin';
                        break;
                    case 'on_hold':
                        $event_badge_class = 'ihd-badge-no';
                        break;
                    case 'pending_cancel':
                        $event_badge_class = 'ihd-badge-reason';
                        break;
                }
                $event_badge = $event_type_val ? '<span class="ihd-badge ' . $event_badge_class . '">' . $event_type_val . '</span>' : '<span class="ihd-audit-muted">—</span>';

                // Intentional badge.
                $intentional_badge = $row['intentional']
                    ? '<span class="ihd-badge ihd-badge-yes">Intentional</span>'
                    : '<span class="ihd-badge ihd-badge-no">Unintentional</span>';

                // Status transition.
                $old_s = esc_html( $row['old_status'] ?? '' );
                $new_s = esc_html( $row['new_status'] ?? '' );
                $status_text = ( $old_s && $new_s ) ? $old_s . ' → ' . $new_s : '—';

                // Reason badge.
                $reason_text = esc_html( $row['reason'] );
                if ( ! $row['reason'] ) {
                    $reason_badge = '<span class="ihd-audit-muted">—</span>';
                } elseif ( stripos( $row['reason'], 'Integration' ) !== false || stripos( $row['reason'], 'Gateway Error' ) !== false ) {
                    $reason_badge = '<span class="ihd-badge ihd-badge-danger">' . $reason_text . '</span>';
                } elseif ( stripos( $row['reason'], 'Payment Failed' ) !== false || stripos( $row['reason'], 'Renewal Failed' ) !== false || stripos( $row['reason'], 'Max Retries' ) !== false ) {
                    $reason_badge = '<span class="ihd-badge ihd-badge-warning">' . $reason_text . '</span>';
                } elseif ( stripos( $row['reason'], 'Admin' ) !== false ) {
                    $reason_badge = '<span class="ihd-badge ihd-badge-admin">' . $reason_text . '</span>';
                } elseif ( stripos( $row['reason'], 'Expired' ) !== false || stripos( $row['reason'], 'On-Hold' ) !== false ) {
                    $reason_badge = '<span class="ihd-badge ihd-badge-no">' . $reason_text . '</span>';
                } else {
                    $reason_badge = '<span class="ihd-badge ihd-badge-reason">' . $reason_text . '</span>';
                }

                // Payment info.
                $payment_info = '';
                if ( ! empty( $row['payment_method'] ) ) {
                    $payment_info .= esc_html( $row['payment_method'] );
                }
                if ( ! empty( $row['payment_error_type'] ) && 'unknown' !== $row['payment_error_type'] ) {
                    $err_class = 'integration_error' === $row['payment_error_type'] ? 'ihd-badge-danger' : 'ihd-badge-warning';
                    $payment_info .= '<br><span class="ihd-badge ' . $err_class . '">' . esc_html( $row['payment_error_type'] ) . '</span>';
                }
                if ( empty( $payment_info ) ) {
                    $payment_info = '<span class="ihd-audit-muted">—</span>';
                }

                // Amount.
                $amount_text = '<span class="ihd-audit-muted">—</span>';
                if ( floatval( $row['subscription_amount'] ) > 0 ) {
                    $amount_text = esc_html( $row['currency'] ) . ' ' . esc_html( number_format( $row['subscription_amount'], 2 ) );
                    if ( ! empty( $row['billing_period'] ) ) {
                        $amount_text .= ' / ' . esc_html( $row['billing_period'] );
                    }
                }

                // Triggered by.
                $by_user_id = absint( $row['by_user_id'] ?? 0 );
                if ( $by_user_id > 0 ) {
                    $by_user = get_userdata( $by_user_id );
                    $feedback_text = $by_user
                        ? '<strong>' . esc_html( $by_user->display_name ) . '</strong><br><span class="ihd-audit-email">' . esc_html( $by_user->user_email ) . '</span>'
                        : 'User #' . $by_user_id;
                } else {
                    $feedback_text = '<span class="ihd-audit-muted">System / Cron</span>';
                }

                // Date.
                $date_formatted = wp_date( 'M j, Y', strtotime( $row['created_at'] ) );
                $time_formatted = wp_date( 'g:i a', strtotime( $row['created_at'] ) );
                $human_time     = human_time_diff( strtotime( $row['created_at'] ), current_time( 'timestamp' ) ) . ' ago';

                $sub_link = admin_url( 'post.php?post=' . absint( $row['subscription_id'] ) . '&action=edit' );

                // Detail tooltip.
                $detail_attr = '';
                if ( ! empty( $row['detail'] ) ) {
                    $detail_attr = ' title="' . esc_attr( wp_strip_all_tags( $row['detail'] ) ) . '"';
                }

                $html .= '<tr' . $detail_attr . '>';
                $html .= '<td class="column-id">#' . esc_html( $row['id'] ) . '</td>';
                $html .= '<td class="column-subscription"><a href="' . esc_url( $sub_link ) . '" target="_blank">#' . esc_html( $row['subscription_id'] ) . '</a></td>';
                $html .= '<td class="column-user">' . $customer_info . '</td>';
                $html .= '<td class="column-event">' . $event_badge . '</td>';
                $html .= '<td class="column-status">' . $status_text . '</td>';
                $html .= '<td class="column-type">' . $intentional_badge . '</td>';
                $html .= '<td class="column-reason">' . $reason_badge . '</td>';
                $html .= '<td class="column-payment">' . $payment_info . '</td>';
                $html .= '<td class="column-amount">' . $amount_text . '</td>';
                $html .= '<td class="column-date"><strong>' . esc_html( $date_formatted ) . '</strong><br><span class="ihd-audit-muted">' . esc_html( $time_formatted ) . '</span><br><span class="ihd-audit-muted ihd-audit-human-time">' . esc_html( $human_time ) . '</span></td>';
                $html .= '</tr>';
            }
        }

        // Pagination
        $total_pages = max( 1, ceil( $total / self::PER_PAGE ) );
        $showing_from = $total > 0 ? $offset + 1 : 0;
        $showing_to   = min( $offset + self::PER_PAGE, $total );

        wp_send_json_success( array(
            'html'        => $html,
            'total'       => $total,
            'total_pages' => $total_pages,
            'page'        => $page,
            'from'        => $showing_from,
            'to'          => $showing_to,
        ) );
    }

    public function save_settings() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( self::NONCE_ACTION, 'ihd_vip_nonce' );

        $user_ids = isset( $_POST['ihd_vip_users'] ) ? array_map( 'absint', (array) $_POST['ihd_vip_users'] ) : array();
        $user_ids = array_filter( $user_ids );

        update_option( self::OPTION_KEY, $user_ids );

        wp_safe_redirect( add_query_arg(
            array(
                'page'  => 'ihd-vip-subscriptions',
                'saved' => '1',
            ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    public function render_settings_page() {
        $saved_users = get_option( self::OPTION_KEY, array() );
        $is_dev_mode = IHD_VIP_User_Scope_Gate::is_development_mode();
        $user_count  = count( $saved_users );
        ?>
        <div class="wrap ihd-vip-wrap">

            <h1 class="ihd-page-title">IHD VIP Subscriptions</h1>

            <?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Settings saved successfully.</p>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="ihd-page-header">
                <p class="ihd-page-subtitle">Manage VIP access and view cancellation audit logs</p>
                <div class="ihd-header-badge">
                    <span class="ihd-mode-badge <?php echo $is_dev_mode ? 'ihd-mode-dev' : 'ihd-mode-prod'; ?>" id="ihd-mode-badge">
                        <span class="dashicons <?php echo $is_dev_mode ? 'dashicons-lock' : 'dashicons-unlock'; ?>" id="ihd-mode-icon"></span>
                        <span id="ihd-mode-label"><?php echo $is_dev_mode ? 'Development Mode' : 'Production Mode'; ?></span>
                    </span>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="ihd-stats-row">
                <div class="ihd-stat-card">
                    <div class="ihd-stat-icon ihd-stat-icon-users"><span class="dashicons dashicons-groups"></span></div>
                    <div class="ihd-stat-content">
                        <span class="ihd-stat-number"><?php echo esc_html( $user_count ); ?></span>
                        <span class="ihd-stat-label">Allowed Users</span>
                    </div>
                </div>
                <div class="ihd-stat-card">
                    <div class="ihd-stat-icon ihd-stat-icon-gate"><span class="dashicons dashicons-shield"></span></div>
                    <div class="ihd-stat-content">
                        <span class="ihd-stat-number" id="ihd-stat-mode"><?php echo $is_dev_mode ? 'Scoped' : 'Open'; ?></span>
                        <span class="ihd-stat-label">Access Mode</span>
                    </div>
                </div>
                <div class="ihd-stat-card">
                    <div class="ihd-stat-icon ihd-stat-icon-logs"><span class="dashicons dashicons-list-view"></span></div>
                    <div class="ihd-stat-content">
                        <span class="ihd-stat-number" id="ihd-audit-total-count">—</span>
                        <span class="ihd-stat-label">Audit Entries</span>
                    </div>
                </div>
            </div>

            <!-- Scope Mode Toggle Card -->
            <div class="ihd-card">
                <div class="ihd-card-header">
                    <h2><span class="dashicons dashicons-shield-alt"></span> Scope Mode</h2>
                </div>
                <div class="ihd-card-body">
                    <p class="ihd-card-desc">Toggle between <strong>Development Mode</strong> (only selected users see VIP features) and <strong>Production Mode</strong> (all logged-in users can access).</p>
                    <div class="ihd-toggle-wrap">
                        <label class="ihd-toggle">
                            <input type="checkbox" id="ihd-scope-toggle" <?php checked( ! $is_dev_mode ); ?>>
                            <span class="ihd-toggle-slider"></span>
                        </label>
                        <span class="ihd-toggle-label" id="ihd-toggle-label">
                            <?php echo $is_dev_mode ? 'Development Mode — Only selected users can access VIP features' : 'Production Mode — All logged-in users can access VIP features'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Allowed Users Card -->
            <div class="ihd-card" id="ihd-users-card" style="<?php echo $is_dev_mode ? '' : 'display:none;'; ?>">
                <div class="ihd-card-header">
                    <h2><span class="dashicons dashicons-admin-users"></span> Allowed Users</h2>
                </div>
                <div class="ihd-card-body">
                    <p class="ihd-card-desc">Search and select users who should have access to VIP subscription features. Only these users will see the VIP interface while in development mode.</p>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="ihd_vip_save_settings">
                        <?php wp_nonce_field( self::NONCE_ACTION, 'ihd_vip_nonce' ); ?>

                        <div class="ihd-select-wrap">
                            <label for="ihd_vip_users" class="ihd-select-label">Select Users</label>
                            <select
                                id="ihd_vip_users"
                                name="ihd_vip_users[]"
                                class="ihd-vip-user-select"
                                multiple="multiple"
                            >
                                <?php
                                if ( ! empty( $saved_users ) ) {
                                    foreach ( $saved_users as $uid ) {
                                        $user = get_userdata( $uid );
                                        if ( $user ) {
                                            printf(
                                                '<option value="%d" selected="selected">%s (%s) — #%d</option>',
                                                esc_attr( $user->ID ),
                                                esc_html( $user->display_name ),
                                                esc_html( $user->user_email ),
                                                esc_html( $user->ID )
                                            );
                                        }
                                    }
                                }
                                ?>
                            </select>
                            <p class="ihd-select-hint">Start typing a name or email to search. You can add multiple users.</p>
                        </div>

                        <div class="ihd-card-footer">
                            <?php submit_button( 'Save Settings', 'primary ihd-save-btn', 'submit', false ); ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Audit Logs Card -->
            <div class="ihd-card ihd-card-audit">
                <div class="ihd-card-header">
                    <h2><span class="dashicons dashicons-clipboard"></span> Subscription Audit Logs</h2>
                    <span class="ihd-audit-summary" id="ihd-audit-summary"></span>
                </div>
                <div class="ihd-card-body">
                    <!-- Filters -->
                    <div class="ihd-audit-filters">
                        <div class="ihd-filter-group">
                            <label for="ihd-filter-event-type">Event Type</label>
                            <select id="ihd-filter-event-type" class="ihd-filter-select">
                                <option value="">All Events</option>
                                <option value="cancellation">Cancellation</option>
                                <option value="payment_failure">Payment Failure</option>
                                <option value="expiration">Expiration</option>
                                <option value="on_hold">On-Hold</option>
                                <option value="pending_cancel">Pending Cancel</option>
                            </select>
                        </div>
                        <div class="ihd-filter-group">
                            <label for="ihd-filter-reason">Reason</label>
                            <select id="ihd-filter-reason" class="ihd-filter-select">
                                <option value="">All Reasons</option>
                                <optgroup label="User-Initiated">
                                    <option value="Too expensive">Too expensive</option>
                                    <option value="Not using the benefits enough">Not using benefits</option>
                                    <option value="Found an alternative">Found alternative</option>
                                    <option value="Just need a break">Need a break</option>
                                    <option value="Other">Other</option>
                                </optgroup>
                                <optgroup label="System-Detected">
                                    <option value="Renewal Payment Failed">Renewal Payment Failed</option>
                                    <option value="Renewal Failed (Insufficient Funds)">Renewal Failed (Insufficient Funds)</option>
                                    <option value="Renewal Failed (Expired Card)">Renewal Failed (Expired Card)</option>
                                    <option value="Renewal Failed (Payment Declined)">Renewal Failed (Payment Declined)</option>
                                    <option value="Integration/Gateway Error">Integration/Gateway Error</option>
                                    <option value="Auto-cancelled (Payment Failed)">Auto-cancelled (Payment Failed)</option>
                                    <option value="Auto-cancelled (Max Retries Exceeded)">Auto-cancelled (Max Retries)</option>
                                    <option value="On-Hold (Payment Failed)">On-Hold (Payment Failed)</option>
                                    <option value="On-Hold (Integration/Gateway Error)">On-Hold (Gateway Error)</option>
                                    <option value="Subscription Expired">Subscription Expired</option>
                                    <option value="Subscription On-Hold">Subscription On-Hold</option>
                                    <option value="Pending Cancellation">Pending Cancellation</option>
                                    <option value="System Cancelled">System Cancelled</option>
                                </optgroup>
                                <optgroup label="Admin">
                                    <option value="Admin Cancelled">Admin Cancelled</option>
                                    <option value="Admin On-hold">Admin On-hold</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="ihd-filter-group ihd-filter-group-customer">
                            <label for="ihd-filter-user">Customer</label>
                            <select id="ihd-filter-user" class="ihd-filter-customer-select" data-placeholder="Search by name, email, or ID…" style="min-width:240px;">
                                <option value=""></option>
                            </select>
                        </div>
                        <div class="ihd-filter-group">
                            <label for="ihd-filter-date-from">From</label>
                            <input type="date" id="ihd-filter-date-from" class="ihd-filter-input">
                        </div>
                        <div class="ihd-filter-group">
                            <label for="ihd-filter-date-to">To</label>
                            <input type="date" id="ihd-filter-date-to" class="ihd-filter-input">
                        </div>
                        <div class="ihd-filter-group ihd-filter-actions">
                            <button type="button" id="ihd-filter-apply" class="button button-primary">Filter</button>
                            <button type="button" id="ihd-filter-reset" class="button">Reset</button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="ihd-audit-table-wrap">
                        <table class="ihd-audit-table">
                            <thead>
                                <tr>
                                    <th class="column-id">ID</th>
                                    <th class="column-subscription">Subscription</th>
                                    <th class="column-user">Customer</th>
                                    <th class="column-event">Event</th>
                                    <th class="column-status">Status</th>
                                    <th class="column-type">Intent</th>
                                    <th class="column-reason">Reason</th>
                                    <th class="column-payment">Payment</th>
                                    <th class="column-amount">Amount</th>
                                    <th class="column-date">Date</th>
                                </tr>
                            </thead>
                            <tbody id="ihd-audit-tbody">
                                <tr><td colspan="10" class="ihd-audit-loading"><span class="spinner is-active"></span> Loading audit logs...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="ihd-audit-pagination" id="ihd-audit-pagination">
                        <span class="ihd-audit-showing" id="ihd-audit-showing"></span>
                        <div class="ihd-audit-page-btns" id="ihd-audit-page-btns"></div>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }

    private function get_inline_js() {
        $nonce    = wp_create_nonce( 'ihd_vip_admin_nonce' );
        $ajax_url = admin_url( 'admin-ajax.php' );

        return <<<JS
        jQuery(function($){
            // Select2 init
            $('.ihd-vip-user-select').select2({
                ajax: {
                    url: '{$ajax_url}',
                    dataType: 'json',
                    delay: 300,
                    data: function(params) {
                        return {
                            action: 'ihd_vip_search_users',
                            nonce: '{$nonce}',
                            term: params.term
                        };
                    },
                    processResults: function(data) { return data; },
                    cache: true
                },
                minimumInputLength: 2,
                placeholder: 'Search users by name or email…',
                allowClear: true,
                width: '100%'
            });

            // Customer filter Select2 (audit log filter)
            var $custFilter = $('#ihd-filter-user');
            $custFilter.select2({
                ajax: {
                    url: '{$ajax_url}',
                    type: 'GET',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'ihd_vip_search_users',
                            nonce: '{$nonce}',
                            term: params.term || ''
                        };
                    },
                    processResults: function(data) {
                        return { results: (data && data.results) ? data.results : [] };
                    },
                    cache: true
                },
                minimumInputLength: 2,
                placeholder: $custFilter.data('placeholder') || 'Search customer…',
                allowClear: true,
                width: '100%',
                escapeMarkup: function(m) { return m; }
            });

            // Scope Mode Toggle
            $('#ihd-scope-toggle').on('change', function() {
                var isProduction = $(this).is(':checked');
                var newMode = isProduction ? 'production' : 'development';
                var toggle = $(this);

                toggle.prop('disabled', true);

                $.post('{$ajax_url}', {
                    action: 'ihd_vip_toggle_scope_mode',
                    nonce: '{$nonce}',
                    mode: newMode
                }, function(resp) {
                    toggle.prop('disabled', false);
                    if (resp.success) {
                        var d = resp.data;
                        $('#ihd-mode-label').text(d.label);
                        $('#ihd-mode-badge').attr('class', 'ihd-mode-badge ' + (d.mode === 'production' ? 'ihd-mode-prod' : 'ihd-mode-dev'));
                        $('#ihd-mode-icon').attr('class', 'dashicons ' + (d.mode === 'production' ? 'dashicons-unlock' : 'dashicons-lock'));
                        $('#ihd-stat-mode').text(d.mode === 'production' ? 'Open' : 'Scoped');
                        $('#ihd-toggle-label').text(d.mode === 'production'
                            ? 'Production Mode — All logged-in users can access VIP features'
                            : 'Development Mode — Only selected users can access VIP features'
                        );

                        if (d.mode === 'production') {
                            $('#ihd-users-card').slideUp(200);
                        } else {
                            $('#ihd-users-card').slideDown(200);
                        }
                    }
                });
            });

            // Audit Logs
            var currentPage = 1;

            function loadAuditLogs(page) {
                page = page || 1;
                currentPage = page;

                var params = {
                    action: 'ihd_vip_audit_logs',
                    nonce: '{$nonce}',
                    paged: page,
                    filter_reason: $('#ihd-filter-reason').val(),
                    filter_event_type: $('#ihd-filter-event-type').val(),
                    filter_user: $('#ihd-filter-user').val(),
                    date_from: $('#ihd-filter-date-from').val(),
                    date_to: $('#ihd-filter-date-to').val()
                };

                $('#ihd-audit-tbody').html('<tr><td colspan="10" class="ihd-audit-loading"><span class="spinner is-active"></span> Loading...</td></tr>');

                $.get('{$ajax_url}', params, function(resp) {
                    if (resp.success) {
                        var d = resp.data;
                        $('#ihd-audit-tbody').html(d.html);
                        $('#ihd-audit-total-count').text(d.total);

                        // Summary
                        if (d.total > 0) {
                            $('#ihd-audit-summary').text(d.total + ' total entries');
                            $('#ihd-audit-showing').text('Showing ' + d.from + '–' + d.to + ' of ' + d.total);
                        } else {
                            $('#ihd-audit-summary').text('No entries');
                            $('#ihd-audit-showing').text('');
                        }

                        // Pagination buttons
                        var btns = '';
                        if (d.total_pages > 1) {
                            btns += '<button class="button ihd-page-btn" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>&laquo; Prev</button>';

                            var start = Math.max(1, page - 2);
                            var end = Math.min(d.total_pages, page + 2);

                            if (start > 1) {
                                btns += '<button class="button ihd-page-btn" data-page="1">1</button>';
                                if (start > 2) btns += '<span class="ihd-page-ellipsis">…</span>';
                            }

                            for (var i = start; i <= end; i++) {
                                btns += '<button class="button ihd-page-btn' + (i === page ? ' button-primary' : '') + '" data-page="' + i + '">' + i + '</button>';
                            }

                            if (end < d.total_pages) {
                                if (end < d.total_pages - 1) btns += '<span class="ihd-page-ellipsis">…</span>';
                                btns += '<button class="button ihd-page-btn" data-page="' + d.total_pages + '">' + d.total_pages + '</button>';
                            }

                            btns += '<button class="button ihd-page-btn" data-page="' + (page + 1) + '"' + (page >= d.total_pages ? ' disabled' : '') + '>Next &raquo;</button>';
                        }
                        $('#ihd-audit-page-btns').html(btns);
                    }
                });
            }

            // Initial load
            loadAuditLogs(1);

            // Pagination clicks
            $(document).on('click', '.ihd-page-btn:not([disabled])', function() {
                loadAuditLogs(parseInt($(this).data('page')));
            });

            // Filters
            $('#ihd-filter-apply').on('click', function() { loadAuditLogs(1); });
            $('#ihd-filter-reset').on('click', function() {
                $('#ihd-filter-reason').val('');
                $('#ihd-filter-event-type').val('');
                $('#ihd-filter-user').val(null).trigger('change');
                $('#ihd-filter-date-from').val('');
                $('#ihd-filter-date-to').val('');
                loadAuditLogs(1);
            });

            // Enter key in filter inputs
            $('.ihd-filter-input, .ihd-filter-select').on('keypress', function(e) {
                if (e.which === 13) { e.preventDefault(); loadAuditLogs(1); }
            });
        });
JS;
    }

    private function get_inline_css() {
        return <<<CSS
        /* Layout */
        .ihd-vip-wrap { max-width: 100%; padding-bottom: 40px; }

        /* Page Header */
        .ihd-page-title { font-size: 23px; font-weight: 600; color: #1d2327; margin: 10px 0 4px; }
        .ihd-page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin: 0 0 20px; padding: 0;
        }
        .ihd-page-subtitle { margin: 0; color: #646970; font-size: 13px; }

        .ihd-mode-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500;
            transition: all .2s;
        }
        .ihd-mode-badge .dashicons { font-size: 16px; width: 16px; height: 16px; }
        .ihd-mode-dev { background: #fef0ef; color: #d63638; border: 1px solid #f0c0bf; }
        .ihd-mode-prod { background: #edfaef; color: #00a32a; border: 1px solid #b8e6c0; }

        /* Toggle Switch */
        .ihd-toggle-wrap { display: flex; align-items: center; gap: 14px; }
        .ihd-toggle { position: relative; display: inline-block; width: 52px; height: 28px; flex-shrink: 0; }
        .ihd-toggle input { opacity: 0; width: 0; height: 0; }
        .ihd-toggle-slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #c3c4c7; border-radius: 28px; transition: .3s;
        }
        .ihd-toggle-slider:before {
            position: absolute; content: ''; height: 22px; width: 22px;
            left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .3s;
            box-shadow: 0 1px 3px rgba(0,0,0,.15);
        }
        .ihd-toggle input:checked + .ihd-toggle-slider { background-color: #00a32a; }
        .ihd-toggle input:checked + .ihd-toggle-slider:before { transform: translateX(24px); }
        .ihd-toggle input:focus + .ihd-toggle-slider { box-shadow: 0 0 0 2px rgba(0,163,42,.3); }
        .ihd-toggle-label { font-size: 13px; color: #50575e; line-height: 1.4; }

        /* Stats Row */
        .ihd-stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .ihd-stat-card {
            background: #fff; border: 1px solid #e0e0e0; border-radius: 8px;
            padding: 20px; display: flex; align-items: center; gap: 16px;
            transition: box-shadow .15s;
        }
        .ihd-stat-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .ihd-stat-icon {
            width: 48px; height: 48px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .ihd-stat-icon .dashicons { font-size: 24px; width: 24px; height: 24px; color: #fff; }
        .ihd-stat-icon-users { background: linear-gradient(135deg, #2271b1, #135e96); }
        .ihd-stat-icon-gate { background: linear-gradient(135deg, #00a32a, #007017); }
        .ihd-stat-icon-logs { background: linear-gradient(135deg, #dba617, #c08c00); }
        .ihd-stat-content { display: flex; flex-direction: column; }
        .ihd-stat-number { font-size: 22px; font-weight: 700; color: #1d2327; line-height: 1.2; }
        .ihd-stat-label { font-size: 12px; color: #646970; text-transform: uppercase; letter-spacing: .5px; }

        /* Cards */
        .ihd-card {
            background: #fff; border: 1px solid #e0e0e0; border-radius: 8px;
            margin-bottom: 24px; overflow: hidden;
        }
        .ihd-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 24px; border-bottom: 1px solid #f0f0f1; background: #fafafa;
        }
        .ihd-card-header h2 {
            margin: 0; font-size: 15px; font-weight: 600; color: #1d2327;
            display: flex; align-items: center; gap: 8px;
        }
        .ihd-card-header h2 .dashicons { color: #2271b1; font-size: 18px; width: 18px; height: 18px; }
        .ihd-card-notice {
            font-size: 12px; color: #996800; background: #fcf0e3; padding: 4px 10px;
            border-radius: 4px; border: 1px solid #f0d9b5;
        }
        .ihd-card-body { padding: 24px; }
        .ihd-card-desc { margin: 0 0 20px; color: #50575e; font-size: 13px; line-height: 1.6; }

        /* Select2 Overrides */
        .ihd-select-wrap { margin-bottom: 20px; max-width: 420px; }
        .ihd-select-label { display: block; font-weight: 600; color: #1d2327; margin-bottom: 6px; font-size: 13px; }
        .ihd-select-hint { margin: 8px 0 0; color: #8c8f94; font-size: 12px; font-style: italic; }

        .ihd-vip-wrap .select2-container--default .select2-selection--multiple {
            border: 1px solid #c3c4c7 !important; border-radius: 6px !important;
            min-height: 44px !important; padding: 4px 8px !important;
            background: #fff !important; transition: border-color .15s, box-shadow .15s;
        }
        .ihd-vip-wrap .select2-container--default.select2-container--focus .select2-selection--multiple,
        .ihd-vip-wrap .select2-container--default.select2-container--open .select2-selection--multiple {
            border-color: #2271b1 !important; box-shadow: 0 0 0 1px #2271b1 !important;
        }
        .ihd-vip-wrap .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background: #f0f6fc !important; border: 1px solid #c5d9ed !important;
            border-radius: 4px !important; padding: 4px 8px !important; margin: 3px 4px 3px 0 !important;
            color: #1d2327 !important; font-size: 13px !important;
        }
        .ihd-vip-wrap .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #2271b1 !important; margin-right: 6px !important; font-weight: 700 !important;
        }
        .ihd-vip-wrap .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #d63638 !important;
        }
        .ihd-vip-wrap .select2-container--default .select2-search--inline .select2-search__field {
            margin-top: 4px !important; font-size: 13px !important;
        }

        .ihd-card-footer { padding-top: 16px; border-top: 1px solid #f0f0f1; }
        .ihd-save-btn.button-primary {
            padding: 6px 24px !important; height: auto !important; font-size: 13px !important;
            border-radius: 4px !important;
        }

        /* Audit Filters */
        .ihd-audit-filters {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;
            padding: 16px 20px; background: #f9f9f9; border: 1px solid #e8e8e8;
            border-radius: 6px; margin-bottom: 20px;
        }
        .ihd-filter-group { display: flex; flex-direction: column; gap: 4px; }
        .ihd-filter-group label { font-size: 11px; font-weight: 600; color: #646970; text-transform: uppercase; letter-spacing: .3px; }
        .ihd-filter-select, .ihd-filter-input {
            padding: 6px 10px; border: 1px solid #c3c4c7; border-radius: 4px;
            font-size: 13px; min-width: 140px; background: #fff;
        }
        .ihd-filter-select { min-width: 200px; }
        .ihd-filter-group-customer { min-width: 240px; }
        .ihd-filter-group-customer .select2-container--default .select2-selection--single {
            height: 32px !important; border: 1px solid #c3c4c7 !important;
            border-radius: 4px !important; padding: 2px 8px !important;
            background: #fff !important; font-size: 13px !important;
        }
        .ihd-filter-group-customer .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px !important; color: #1d2327 !important; padding-left: 0 !important;
        }
        .ihd-filter-group-customer .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 30px !important;
        }
        .ihd-filter-group-customer .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-right: 4px !important; font-size: 16px !important;
        }
        .ihd-filter-group-customer .select2-container--default.select2-container--focus .select2-selection--single,
        .ihd-filter-group-customer .select2-container--default.select2-container--open .select2-selection--single {
            border-color: #2271b1 !important; box-shadow: 0 0 0 1px #2271b1 !important;
        }
        .ihd-filter-select:focus, .ihd-filter-input:focus {
            border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none;
        }
        .ihd-filter-input[type="number"] { width: 100px; min-width: 100px; }
        .ihd-filter-input[type="date"] { min-width: 130px; }
        .ihd-filter-actions { flex-direction: row; gap: 6px; align-items: flex-end; }

        /* Audit Table */
        .ihd-audit-table-wrap { overflow-x: auto; }
        .ihd-audit-table {
            width: 100%; border-collapse: collapse; font-size: 13px;
        }
        .ihd-audit-table thead th {
            background: #f6f7f7; padding: 10px 12px; text-align: left;
            font-weight: 600; color: #1d2327; border-bottom: 2px solid #dcdcde;
            font-size: 12px; text-transform: uppercase; letter-spacing: .3px;
            white-space: nowrap;
        }
        .ihd-audit-table tbody td {
            padding: 12px; border-bottom: 1px solid #f0f0f1; vertical-align: top;
            color: #1d2327;
        }
        .ihd-audit-table tbody tr:hover { background: #f9fbfd; }
        .ihd-audit-table tbody tr[title] { cursor: help; }
        .ihd-audit-table .column-id { width: 50px; color: #8c8f94; }
        .ihd-audit-table .column-subscription { width: 100px; }
        .ihd-audit-table .column-subscription a { color: #2271b1; text-decoration: none; font-weight: 500; }
        .ihd-audit-table .column-subscription a:hover { color: #135e96; text-decoration: underline; }
        .ihd-audit-table .column-user { min-width: 140px; }
        .ihd-audit-table .column-event { width: 110px; }
        .ihd-audit-table .column-status { width: 110px; white-space: nowrap; font-size: 12px; color: #50575e; }
        .ihd-audit-table .column-type { width: 100px; }
        .ihd-audit-table .column-reason { width: 130px; }
        .ihd-audit-table .column-payment { min-width: 120px; font-size: 12px; }
        .ihd-audit-table .column-amount { width: 110px; white-space: nowrap; font-size: 12px; }
        .ihd-audit-table .column-date { width: 120px; white-space: nowrap; }

        .ihd-audit-email { color: #646970; font-size: 12px; }
        .ihd-audit-muted { color: #a7aaad; font-size: 12px; }
        .ihd-audit-human-time { font-style: italic; }
        .ihd-audit-empty, .ihd-audit-loading { text-align: center; padding: 40px 12px !important; color: #646970; }
        .ihd-audit-loading .spinner { float: none; margin: 0 8px 0 0; }

        .ihd-badge {
            display: inline-block; padding: 3px 10px; border-radius: 12px;
            font-size: 11px; font-weight: 600; line-height: 1.4; white-space: nowrap;
        }
        .ihd-badge-yes { background: #edfaef; color: #007017; }
        .ihd-badge-no { background: #f0f6fc; color: #2271b1; }
        .ihd-badge-reason { background: #fcf0e3; color: #996800; }
        .ihd-badge-danger { background: #fef0ef; color: #d63638; }
        .ihd-badge-warning { background: #fef8ee; color: #9a6700; }
        .ihd-badge-admin { background: #f0f0f1; color: #50575e; }

        /* Pagination */
        .ihd-audit-pagination {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 0 0; margin-top: 4px; border-top: 1px solid #f0f0f1;
        }
        .ihd-audit-showing { font-size: 12px; color: #646970; }
        .ihd-audit-summary { font-size: 12px; color: #646970; font-weight: 400; }
        .ihd-audit-page-btns { display: flex; gap: 4px; align-items: center; }
        .ihd-page-btn.button { min-width: 32px; text-align: center; padding: 0 8px; height: 30px; line-height: 28px; }
        .ihd-page-ellipsis { color: #a7aaad; padding: 0 4px; }

        /* Responsive */
        @media (min-width: 1200px) {
            .ihd-select-wrap { max-width: 420px; }
        }
        @media (max-width: 1199px) and (min-width: 783px) {
            .ihd-select-wrap { max-width: 50%; }
        }
        @media (max-width: 782px) {
            .ihd-stats-row { grid-template-columns: 1fr; }
            .ihd-page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .ihd-audit-filters { flex-direction: column; }
            .ihd-filter-select, .ihd-filter-input { width: 100%; min-width: auto; }
            .ihd-select-wrap { max-width: 100%; }
        }
CSS;
    }
}
