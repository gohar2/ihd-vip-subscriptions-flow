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

    public function ajax_audit_logs() {
        check_ajax_referer( 'ihd_vip_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ihd_subscription_audit';

        $page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $reason   = isset( $_GET['filter_reason'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_reason'] ) ) : '';
        $user_id  = isset( $_GET['filter_user'] ) ? absint( $_GET['filter_user'] ) : 0;
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
        $date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

        $where = array( '1=1' );
        $params = array();

        if ( $reason !== '' ) {
            $where[] = 'a.reason = %s';
            $params[] = $reason;
        }

        if ( $user_id > 0 ) {
            $where[] = 'pm.meta_value = %d';
            $params[] = $user_id;
        }

        if ( $date_from !== '' ) {
            $where[] = 'a.created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        if ( $date_to !== '' ) {
            $where[] = 'a.created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $where );

        // Join with postmeta to get subscription owner
        $join = "LEFT JOIN {$wpdb->postmeta} pm ON a.subscription_id = pm.post_id AND pm.meta_key = '_customer_user'";

        // Count
        $count_sql = "SELECT COUNT(*) FROM {$table} a {$join} WHERE {$where_sql}";
        if ( ! empty( $params ) ) {
            $count_sql = $wpdb->prepare( $count_sql, $params );
        }
        $total = (int) $wpdb->get_var( $count_sql );

        $offset = ( $page - 1 ) * self::PER_PAGE;

        // Data
        $data_sql = "SELECT a.*, pm.meta_value AS user_id FROM {$table} a {$join} WHERE {$where_sql} ORDER BY a.created_at DESC LIMIT %d OFFSET %d";
        $data_params = array_merge( $params, array( self::PER_PAGE, $offset ) );
        $rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

        // Build HTML rows
        $html = '';
        if ( empty( $rows ) ) {
            $html = '<tr><td colspan="7" class="ihd-audit-empty">No audit log entries found.</td></tr>';
        } else {
            foreach ( $rows as $row ) {
                $user_info = '—';
                if ( ! empty( $row['user_id'] ) ) {
                    $u = get_userdata( (int) $row['user_id'] );
                    if ( $u ) {
                        $user_info = '<strong>' . esc_html( $u->display_name ) . '</strong><br><span class="ihd-audit-email">' . esc_html( $u->user_email ) . '</span>';
                    }
                }

                $intentional_badge = $row['intentional']
                    ? '<span class="ihd-badge ihd-badge-yes">User-initiated</span>'
                    : '<span class="ihd-badge ihd-badge-no">System</span>';

                $reason_badge = $row['reason']
                    ? '<span class="ihd-badge ihd-badge-reason">' . esc_html( $row['reason'] ) . '</span>'
                    : '<span class="ihd-audit-muted">—</span>';

                $feedback_text = ! empty( $row['text'] )
                    ? '<div class="ihd-audit-feedback">' . esc_html( wp_trim_words( $row['text'], 20 ) ) . '</div>'
                    : '<span class="ihd-audit-muted">No feedback</span>';

                $date_formatted = wp_date( 'M j, Y', strtotime( $row['created_at'] ) );
                $time_formatted = wp_date( 'g:i a', strtotime( $row['created_at'] ) );
                $human_time = human_time_diff( strtotime( $row['created_at'] ), current_time( 'timestamp' ) ) . ' ago';

                $sub_link = admin_url( 'post.php?post=' . absint( $row['subscription_id'] ) . '&action=edit' );

                $html .= '<tr>';
                $html .= '<td class="column-id">#' . esc_html( $row['id'] ) . '</td>';
                $html .= '<td class="column-subscription"><a href="' . esc_url( $sub_link ) . '" target="_blank">#' . esc_html( $row['subscription_id'] ) . '</a></td>';
                $html .= '<td class="column-user">' . $user_info . '</td>';
                $html .= '<td class="column-type">' . $intentional_badge . '</td>';
                $html .= '<td class="column-reason">' . $reason_badge . '</td>';
                $html .= '<td class="column-feedback">' . $feedback_text . '</td>';
                $html .= '<td class="column-date"><strong>' . esc_html( $date_formatted ) . '</strong><br><span class="ihd-audit-muted">' . esc_html( $time_formatted ) . '</span><br><span class="ihd-audit-muted ihd-audit-human-time">' . esc_html( $human_time ) . '</span></td>';
                $html .= '</tr>';
            }
        }

        // Pagination
        $total_pages = max( 1, ceil( $total / self::PER_PAGE ) );
        $showing_from = $total > 0 ? $offset + 1 : 0;
        $showing_to = min( $offset + self::PER_PAGE, $total );

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
        $gate_active = file_exists( IHD_VIP_PATH . 'includes/class-user-scope-gate.php' );
        $user_count  = count( $saved_users );
        ?>
        <div class="wrap ihd-vip-wrap">

            <?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Settings saved successfully.</p>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="ihd-page-header">
                <div>
                    <h1>IHD VIP Subscriptions</h1>
                    <p class="ihd-page-subtitle">Manage VIP access and view cancellation audit logs</p>
                </div>
                <div class="ihd-header-badge">
                    <?php if ( $gate_active ) : ?>
                        <span class="ihd-mode-badge ihd-mode-dev"><span class="dashicons dashicons-lock"></span> Development Mode</span>
                    <?php else : ?>
                        <span class="ihd-mode-badge ihd-mode-prod"><span class="dashicons dashicons-unlock"></span> Production Mode</span>
                    <?php endif; ?>
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
                        <span class="ihd-stat-number"><?php echo $gate_active ? 'Scoped' : 'Open'; ?></span>
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

            <!-- Allowed Users Card -->
            <div class="ihd-card">
                <div class="ihd-card-header">
                    <h2><span class="dashicons dashicons-admin-users"></span> Allowed Users</h2>
                    <?php if ( ! $gate_active ) : ?>
                        <span class="ihd-card-notice">Scope gate is disabled — user selection has no effect</span>
                    <?php endif; ?>
                </div>
                <div class="ihd-card-body">
                    <?php if ( $gate_active ) : ?>
                        <p class="ihd-card-desc">Search and select users who should have access to VIP subscription features. Only these users will see the VIP interface while in development mode.</p>
                    <?php else : ?>
                        <p class="ihd-card-desc">The scope gate file has been removed. All users currently have access. To restrict access, restore <code>includes/class-user-scope-gate.php</code>.</p>
                    <?php endif; ?>

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
                                <?php echo $gate_active ? '' : 'disabled'; ?>
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
                            <?php submit_button( 'Save Settings', 'primary ihd-save-btn', 'submit', false, $gate_active ? array() : array( 'disabled' => 'disabled' ) ); ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Audit Logs Card -->
            <div class="ihd-card ihd-card-audit">
                <div class="ihd-card-header">
                    <h2><span class="dashicons dashicons-clipboard"></span> Cancellation Audit Logs</h2>
                    <span class="ihd-audit-summary" id="ihd-audit-summary"></span>
                </div>
                <div class="ihd-card-body">
                    <!-- Filters -->
                    <div class="ihd-audit-filters">
                        <div class="ihd-filter-group">
                            <label for="ihd-filter-reason">Reason</label>
                            <select id="ihd-filter-reason" class="ihd-filter-select">
                                <option value="">All Reasons</option>
                                <option value="Too expensive">Too expensive</option>
                                <option value="Not using the benefits enough">Not using benefits</option>
                                <option value="Found an alternative">Found alternative</option>
                                <option value="Just need a break">Need a break</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="ihd-filter-group">
                            <label for="ihd-filter-user">User ID</label>
                            <input type="number" id="ihd-filter-user" class="ihd-filter-input" placeholder="e.g. 123" min="1">
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
                                    <th class="column-type">Type</th>
                                    <th class="column-reason">Reason</th>
                                    <th class="column-feedback">Feedback</th>
                                    <th class="column-date">Date</th>
                                </tr>
                            </thead>
                            <tbody id="ihd-audit-tbody">
                                <tr><td colspan="7" class="ihd-audit-loading"><span class="spinner is-active"></span> Loading audit logs...</td></tr>
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
                    filter_user: $('#ihd-filter-user').val(),
                    date_from: $('#ihd-filter-date-from').val(),
                    date_to: $('#ihd-filter-date-to').val()
                };

                $('#ihd-audit-tbody').html('<tr><td colspan="7" class="ihd-audit-loading"><span class="spinner is-active"></span> Loading...</td></tr>');

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
                            // Prev
                            btns += '<button class="button ihd-page-btn" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>&laquo; Prev</button>';

                            // Page numbers
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

                            // Next
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
                $('#ihd-filter-user').val('');
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
        .ihd-page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin: 10px 0 20px; padding: 0;
        }
        .ihd-page-header h1 { margin: 0 0 2px; font-size: 23px; font-weight: 600; color: #1d2327; }
        .ihd-page-subtitle { margin: 0; color: #646970; font-size: 13px; }

        .ihd-mode-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500;
        }
        .ihd-mode-badge .dashicons { font-size: 16px; width: 16px; height: 16px; }
        .ihd-mode-dev { background: #fef0ef; color: #d63638; border: 1px solid #f0c0bf; }
        .ihd-mode-prod { background: #edfaef; color: #00a32a; border: 1px solid #b8e6c0; }

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
        .ihd-select-wrap { margin-bottom: 20px; }
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
        .ihd-audit-table .column-id { width: 50px; color: #8c8f94; }
        .ihd-audit-table .column-subscription { width: 100px; }
        .ihd-audit-table .column-subscription a { color: #2271b1; text-decoration: none; font-weight: 500; }
        .ihd-audit-table .column-subscription a:hover { color: #135e96; text-decoration: underline; }
        .ihd-audit-table .column-user { min-width: 160px; }
        .ihd-audit-table .column-type { width: 110px; }
        .ihd-audit-table .column-reason { width: 140px; }
        .ihd-audit-table .column-feedback { min-width: 180px; max-width: 280px; }
        .ihd-audit-table .column-date { width: 120px; white-space: nowrap; }

        .ihd-audit-email { color: #646970; font-size: 12px; }
        .ihd-audit-muted { color: #a7aaad; font-size: 12px; }
        .ihd-audit-human-time { font-style: italic; }
        .ihd-audit-feedback { color: #50575e; font-size: 12px; line-height: 1.5; }
        .ihd-audit-empty, .ihd-audit-loading { text-align: center; padding: 40px 12px !important; color: #646970; }
        .ihd-audit-loading .spinner { float: none; margin: 0 8px 0 0; }

        .ihd-badge {
            display: inline-block; padding: 3px 10px; border-radius: 12px;
            font-size: 11px; font-weight: 600; line-height: 1.4; white-space: nowrap;
        }
        .ihd-badge-yes { background: #edfaef; color: #007017; }
        .ihd-badge-no { background: #f0f6fc; color: #2271b1; }
        .ihd-badge-reason { background: #fcf0e3; color: #996800; }

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
        @media (max-width: 782px) {
            .ihd-stats-row { grid-template-columns: 1fr; }
            .ihd-page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .ihd-audit-filters { flex-direction: column; }
            .ihd-filter-select, .ihd-filter-input { width: 100%; min-width: auto; }
        }
CSS;
    }
}
