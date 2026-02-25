<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IHD_VIP_Admin {

    const OPTION_KEY = 'ihd_vip_scoped_users';
    const NONCE_ACTION = 'ihd_vip_admin_save';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_ihd_vip_search_users', array( $this, 'ajax_search_users' ) );
        add_action( 'admin_post_ihd_vip_save_settings', array( $this, 'save_settings' ) );
    }

    /**
     * Add submenu page under WooCommerce.
     */
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

    /**
     * Enqueue Select2 and admin JS only on our settings page.
     */
    public function enqueue_assets( $hook ) {
        if ( 'woocommerce_page_ihd-vip-subscriptions' !== $hook ) {
            return;
        }

        // Select2 (bundled with WooCommerce).
        wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC_VERSION );
        wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', array( 'jquery' ), WC_VERSION, true );

        // Inline admin script.
        wp_add_inline_script( 'select2', $this->get_inline_js() );

        // Minimal admin styles.
        wp_add_inline_style( 'select2', $this->get_inline_css() );
    }

    /**
     * AJAX handler: search users by name or email for Select2.
     */
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
     * Handle settings form submission.
     */
    public function save_settings() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( self::NONCE_ACTION, 'ihd_vip_nonce' );

        $user_ids = isset( $_POST['ihd_vip_users'] ) ? array_map( 'absint', (array) $_POST['ihd_vip_users'] ) : array();
        $user_ids = array_filter( $user_ids ); // Remove zeros.

        update_option( self::OPTION_KEY, $user_ids );

        wp_safe_redirect( add_query_arg(
            array(
                'page'    => 'ihd-vip-subscriptions',
                'saved'   => '1',
            ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        $saved_users = get_option( self::OPTION_KEY, array() );
        $gate_active = file_exists( IHD_VIP_PATH . 'includes/class-user-scope-gate.php' );
        ?>
        <div class="wrap ihd-vip-admin-wrap">
            <h1>IHD VIP Subscriptions — Settings</h1>

            <?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Settings saved successfully.</p>
                </div>
            <?php endif; ?>

            <div class="ihd-vip-status-box">
                <h3>Scope Gate Status</h3>
                <?php if ( $gate_active ) : ?>
                    <p class="ihd-status-active">
                        <span class="dashicons dashicons-lock"></span>
                        <strong>Development Mode</strong> — Plugin loads only for selected users below.
                    </p>
                    <p class="description">
                        To switch to production mode (all users), delete
                        <code>includes/class-user-scope-gate.php</code> from the plugin folder.
                    </p>
                <?php else : ?>
                    <p class="ihd-status-production">
                        <span class="dashicons dashicons-unlock"></span>
                        <strong>Production Mode</strong> — Plugin is active for all users.
                    </p>
                    <p class="description">
                        The scope gate file has been removed. The user selection below has no effect.
                    </p>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="ihd_vip_save_settings">
                <?php wp_nonce_field( self::NONCE_ACTION, 'ihd_vip_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ihd_vip_users">Allowed Users</label>
                        </th>
                        <td>
                            <select
                                id="ihd_vip_users"
                                name="ihd_vip_users[]"
                                class="ihd-vip-user-select"
                                multiple="multiple"
                                style="width: 400px;"
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
                            <p class="description">
                                Search and select users who should see the VIP subscription features.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Settings', 'primary', 'submit', true, $gate_active ? array() : array( 'disabled' => 'disabled' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Inline JS for Select2 user search.
     */
    private function get_inline_js() {
        $nonce    = wp_create_nonce( 'ihd_vip_admin_nonce' );
        $ajax_url = admin_url( 'admin-ajax.php' );

        return "
        jQuery(function($){
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
                    processResults: function(data) {
                        return data;
                    },
                    cache: true
                },
                minimumInputLength: 2,
                placeholder: 'Search users by name or email...',
                allowClear: true
            });
        });
        ";
    }

    /**
     * Inline CSS for the admin page.
     */
    private function get_inline_css() {
        return "
        .ihd-vip-admin-wrap { max-width: 800px; }
        .ihd-vip-status-box {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-left: 4px solid #0073aa;
            padding: 12px 20px;
            margin: 20px 0;
        }
        .ihd-vip-status-box h3 { margin-top: 0; }
        .ihd-status-active { color: #d63638; }
        .ihd-status-active .dashicons { vertical-align: text-bottom; }
        .ihd-status-production { color: #00a32a; }
        .ihd-status-production .dashicons { vertical-align: text-bottom; }
        ";
    }
}
