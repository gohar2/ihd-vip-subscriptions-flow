<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IHD_VIP_Modal_Renderer {

    public function __construct() {
        add_action( 'wp_footer', array( $this, 'render_modals' ) );
    }

    /**
     * Render modal HTML, CSS, and JS on the My Account page only.
     */
    public function render_modals() {

        if ( ! is_account_page() ) {
            return;
        }

        $nonce    = wp_create_nonce( 'ihd_vip_nonce' );
        $ajax_url = admin_url( 'admin-ajax.php' );

        $this->render_styles();
        $this->render_overlay();
        $this->render_cancel_modal();
        $this->render_switch_modal();
        $this->render_scripts( $nonce, $ajax_url );
    }

    /**
     * Modal overlay.
     */
    private function render_overlay() {
        ?>
        <div class="ihd-vip-overlay" id="ihd-vip-overlay"></div>
        <?php
    }

    /**
     * Cancel confirmation modal.
     */
    private function render_cancel_modal() {
        ?>
        <div id="ihd-vip-cancel-modal" class="ihd-vip-modal" role="dialog" aria-labelledby="ihd-cancel-title" aria-modal="true">
            <div class="ihd-vip-modal-header">
                <h3 id="ihd-cancel-title">Confirm Cancellation</h3>
                <button type="button" class="ihd-vip-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="ihd-vip-modal-body">
                <p>We're sorry to see you go. Please let us know why you're cancelling:</p>

                <div class="ihd-vip-reasons">
                    <label><input type="radio" name="ihd_cancel_reason" value="Too expensive"> Too expensive</label>
                    <label><input type="radio" name="ihd_cancel_reason" value="Not using enough"> Not using enough</label>
                    <label><input type="radio" name="ihd_cancel_reason" value="Found an alternative"> Found an alternative</label>
                    <label><input type="radio" name="ihd_cancel_reason" value="Missing features"> Missing features</label>
                    <label><input type="radio" name="ihd_cancel_reason" value="Other"> Other</label>
                </div>

                <textarea id="ihd-cancel-feedback" rows="3" placeholder="Any additional feedback? (optional)"></textarea>
            </div>
            <div class="ihd-vip-modal-footer">
                <button type="button" class="button ihd-vip-modal-close-btn">Keep Subscription</button>
                <button type="button" class="button button-danger" id="ihd-vip-confirm-cancel">Confirm Cancel</button>
            </div>
            <div class="ihd-vip-loader" style="display:none;">
                <span class="ihd-vip-spinner"></span> Processing...
            </div>
        </div>
        <?php
    }

    /**
     * Switch (Upgrade/Downgrade) modal.
     */
    private function render_switch_modal() {
        ?>
        <div id="ihd-vip-switch-modal" class="ihd-vip-modal ihd-vip-modal-wide" role="dialog" aria-labelledby="ihd-switch-title" aria-modal="true">
            <div class="ihd-vip-modal-header">
                <h3 id="ihd-switch-title">Change Your Plan</h3>
                <button type="button" class="ihd-vip-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="ihd-vip-modal-body">
                <!-- Plan options loaded via AJAX -->
                <div id="ihd-vip-switch-options">
                    <div class="ihd-vip-loader">
                        <span class="ihd-vip-spinner"></span> Loading plans...
                    </div>
                </div>

                <!-- Inline checkout rendered here -->
                <div id="ihd-vip-checkout-container" style="display:none;"></div>
            </div>
            <div class="ihd-vip-loader" id="ihd-vip-switch-loader" style="display:none;">
                <span class="ihd-vip-spinner"></span> Preparing checkout...
            </div>
        </div>
        <?php
    }

    /**
     * Inline CSS for modals.
     */
    private function render_styles() {
        ?>
        <style id="ihd-vip-modal-styles">
            /* ─── Overlay ─── */
            .ihd-vip-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.6);
                z-index: 99998;
                display: none;
                opacity: 0;
                transition: opacity 0.2s ease;
            }
            .ihd-vip-overlay.active {
                opacity: 1;
            }

            /* ─── Modal base ─── */
            .ihd-vip-modal {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) scale(0.92);
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                width: 520px;
                max-width: 94vw;
                max-height: 85vh;
                overflow-y: auto;
                z-index: 99999;
                display: none;
                opacity: 0;
                transition: opacity 0.25s ease, transform 0.25s ease;
            }
            .ihd-vip-modal.active {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
            .ihd-vip-modal-wide {
                width: 680px;
            }

            /* ─── Header ─── */
            .ihd-vip-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px 24px 0;
            }
            .ihd-vip-modal-header h3 {
                margin: 0;
                font-size: 1.25em;
            }
            .ihd-vip-modal-close {
                background: none;
                border: none;
                font-size: 1.5em;
                cursor: pointer;
                color: #999;
                padding: 0 4px;
                line-height: 1;
            }
            .ihd-vip-modal-close:hover {
                color: #333;
            }

            /* ─── Body ─── */
            .ihd-vip-modal-body {
                padding: 16px 24px;
            }

            /* ─── Footer ─── */
            .ihd-vip-modal-footer {
                padding: 16px 24px 20px;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                border-top: 1px solid #eee;
            }

            /* ─── Cancel reasons ─── */
            .ihd-vip-reasons label {
                display: block;
                padding: 6px 0;
                cursor: pointer;
            }
            .ihd-vip-reasons input[type="radio"] {
                margin-right: 8px;
            }
            #ihd-cancel-feedback {
                width: 100%;
                margin-top: 12px;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                resize: vertical;
            }

            /* ─── Buttons ─── */
            .button-danger {
                background: #d63638 !important;
                border-color: #d63638 !important;
                color: #fff !important;
            }
            .button-danger:hover {
                background: #b32d2e !important;
                border-color: #b32d2e !important;
            }

            /* ─── Switch plan cards ─── */
            .ihd-vip-tier-section h4 {
                margin: 16px 0 8px;
                padding-bottom: 4px;
                border-bottom: 2px solid #eee;
            }
            .ihd-vip-tier-section h4.ihd-upgrade-heading {
                border-bottom-color: #00a32a;
                color: #00a32a;
            }
            .ihd-vip-tier-section h4.ihd-downgrade-heading {
                border-bottom-color: #d63638;
                color: #d63638;
            }
            .ihd-vip-plan-card {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 16px;
                margin: 6px 0;
                border: 1px solid #ddd;
                border-radius: 6px;
                cursor: pointer;
                transition: border-color 0.15s ease, background 0.15s ease;
            }
            .ihd-vip-plan-card:hover {
                border-color: #0073aa;
                background: #f0f6fc;
            }
            .ihd-vip-plan-card .plan-name {
                font-weight: 600;
            }
            .ihd-vip-plan-card .plan-price {
                color: #0073aa;
                font-weight: 600;
            }
            .ihd-vip-no-options {
                color: #999;
                font-style: italic;
                padding: 8px 0;
            }

            /* ─── Loader / Spinner ─── */
            .ihd-vip-loader {
                text-align: center;
                padding: 16px;
                color: #666;
            }
            .ihd-vip-spinner {
                display: inline-block;
                width: 18px;
                height: 18px;
                border: 2px solid #ddd;
                border-top-color: #0073aa;
                border-radius: 50%;
                animation: ihd-spin 0.6s linear infinite;
                vertical-align: middle;
                margin-right: 6px;
            }
            @keyframes ihd-spin {
                to { transform: rotate(360deg); }
            }

            /* ─── Inline checkout in modal ─── */
            #ihd-vip-checkout-container {
                margin-top: 16px;
                padding-top: 16px;
                border-top: 1px solid #eee;
            }
            #ihd-vip-checkout-container .woocommerce-checkout {
                max-width: 100%;
            }

            /* ─── Hide any remaining WCS switch links (CSS fallback) ─── */
            /* WCS renders per-item switch links with various class patterns */
            .subscription_details .wcs-switch-link,
            .order_item a[href*="switch-subscription"],
            .woocommerce-orders-table a[href*="switch-subscription"],
            td.order-actions a[href*="switch-subscription"] {
                display: none !important;
            }
        </style>
        <?php
    }

    /**
     * Inline JS for modal interactions and AJAX calls.
     *
     * WCS renders action buttons as:
     *   <a href="#ihd-vip-cancel-{ID}" class="button cancel">Cancel</a>
     *   <a href="#ihd-vip-switch-{ID}" class="button ihd_vip_switch">Upgrade / Downgrade</a>
     *
     * We target by href prefix (reliable) and extract the subscription ID from the hash.
     */
    private function render_scripts( $nonce, $ajax_url ) {
        ?>
        <script id="ihd-vip-modal-scripts">
        jQuery(function($) {

            var IHD_VIP = {
                nonce: '<?php echo esc_js( $nonce ); ?>',
                ajaxUrl: '<?php echo esc_js( $ajax_url ); ?>',
                activeSubId: 0
            };

            /**
             * Extract subscription ID from href like "#ihd-vip-cancel-12345"
             */
            function getSubIdFromHref(href) {
                var match = href.match(/\d+$/);
                return match ? parseInt(match[0], 10) : 0;
            }

            /* ──────────────── Modal Open / Close ──────────────── */

            function openModal(modalId) {
                var $overlay = $('#ihd-vip-overlay');
                var $modal   = $(modalId);

                $overlay.show();
                $modal.show();

                requestAnimationFrame(function() {
                    $overlay.addClass('active');
                    $modal.addClass('active');
                });

                $modal.find('.ihd-vip-modal-close').focus();
            }

            function closeAllModals() {
                var $overlay = $('#ihd-vip-overlay');
                var $modals  = $('.ihd-vip-modal');

                $overlay.removeClass('active');
                $modals.removeClass('active');

                setTimeout(function() {
                    $overlay.hide();
                    $modals.hide();
                }, 250);
            }

            $('#ihd-vip-overlay').on('click', closeAllModals);
            $(document).on('click', '.ihd-vip-modal-close, .ihd-vip-modal-close-btn', closeAllModals);
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') closeAllModals();
            });

            /* ──────────────── Cancel Flow ──────────────── */

            // WCS renders: <a href="#ihd-vip-cancel-{ID}" class="button cancel">Cancel</a>
            $(document).on('click', 'a[href^="#ihd-vip-cancel-"]', function(e) {
                e.preventDefault();
                e.stopPropagation();

                IHD_VIP.activeSubId = getSubIdFromHref($(this).attr('href'));

                // Reset form state.
                $('input[name="ihd_cancel_reason"]').prop('checked', false);
                $('#ihd-cancel-feedback').val('');

                // Reset button/loader state.
                $('#ihd-vip-confirm-cancel').prop('disabled', false);
                $('#ihd-vip-cancel-modal .ihd-vip-loader').hide();
                $('#ihd-vip-cancel-modal .ihd-vip-modal-footer').show();

                openModal('#ihd-vip-cancel-modal');
            });

            // Confirm cancellation.
            $('#ihd-vip-confirm-cancel').on('click', function() {
                var $btn     = $(this);
                var reason   = $('input[name="ihd_cancel_reason"]:checked').val() || '';
                var feedback = $('#ihd-cancel-feedback').val();

                if (!reason) {
                    alert('Please select a reason for cancellation.');
                    return;
                }

                $btn.prop('disabled', true);
                $btn.closest('.ihd-vip-modal').find('.ihd-vip-loader').show();
                $btn.closest('.ihd-vip-modal').find('.ihd-vip-modal-footer').hide();

                $.post(IHD_VIP.ajaxUrl, {
                    action: 'ihd_vip_cancel_subscription',
                    nonce: IHD_VIP.nonce,
                    subscription_id: IHD_VIP.activeSubId,
                    reason: reason,
                    feedback: feedback
                })
                .done(function(resp) {
                    if (resp.success) {
                        location.reload();
                    } else {
                        alert(resp.data && resp.data.message ? resp.data.message : 'An error occurred.');
                        $btn.prop('disabled', false);
                        $btn.closest('.ihd-vip-modal').find('.ihd-vip-loader').hide();
                        $btn.closest('.ihd-vip-modal').find('.ihd-vip-modal-footer').show();
                    }
                })
                .fail(function() {
                    alert('Request failed. Please try again.');
                    $btn.prop('disabled', false);
                    $btn.closest('.ihd-vip-modal').find('.ihd-vip-loader').hide();
                    $btn.closest('.ihd-vip-modal').find('.ihd-vip-modal-footer').show();
                });
            });

            /* ──────────────── Switch Flow ──────────────── */

            // WCS renders: <a href="#ihd-vip-switch-{ID}" class="button ihd_vip_switch">Upgrade / Downgrade</a>
            $(document).on('click', 'a[href^="#ihd-vip-switch-"]', function(e) {
                e.preventDefault();
                e.stopPropagation();

                IHD_VIP.activeSubId = getSubIdFromHref($(this).attr('href'));

                // Reset state.
                $('#ihd-vip-switch-options').html(
                    '<div class="ihd-vip-loader"><span class="ihd-vip-spinner"></span> Loading plans...</div>'
                );
                $('#ihd-vip-checkout-container').hide().html('');

                openModal('#ihd-vip-switch-modal');

                // Load switch options via AJAX.
                $.post(IHD_VIP.ajaxUrl, {
                    action: 'ihd_vip_load_switch_options',
                    nonce: IHD_VIP.nonce,
                    subscription_id: IHD_VIP.activeSubId
                })
                .done(function(resp) {
                    if (!resp.success) {
                        $('#ihd-vip-switch-options').html(
                            '<p class="ihd-vip-no-options">' +
                            (resp.data && resp.data.message ? resp.data.message : 'Could not load plans.') +
                            '</p>'
                        );
                        return;
                    }

                    var html = '';

                    // Upgrades section.
                    if (resp.data.upgrades && resp.data.upgrades.length) {
                        html += '<div class="ihd-vip-tier-section">';
                        html += '<h4 class="ihd-upgrade-heading">&#9650; Upgrade</h4>';
                        $.each(resp.data.upgrades, function(i, plan) {
                            html += '<div class="ihd-vip-plan-card" data-variation="' + plan.variation_id + '">';
                            html += '<span class="plan-name">' + plan.name + '</span>';
                            html += '<span class="plan-price">' + plan.price_html + '</span>';
                            html += '</div>';
                        });
                        html += '</div>';
                    }

                    // Downgrades section.
                    if (resp.data.downgrades && resp.data.downgrades.length) {
                        html += '<div class="ihd-vip-tier-section">';
                        html += '<h4 class="ihd-downgrade-heading">&#9660; Downgrade</h4>';
                        $.each(resp.data.downgrades, function(i, plan) {
                            html += '<div class="ihd-vip-plan-card" data-variation="' + plan.variation_id + '">';
                            html += '<span class="plan-name">' + plan.name + '</span>';
                            html += '<span class="plan-price">' + plan.price_html + '</span>';
                            html += '</div>';
                        });
                        html += '</div>';
                    }

                    if (!html) {
                        html = '<p class="ihd-vip-no-options">No other plans available for switching.</p>';
                    }

                    $('#ihd-vip-switch-options').html(html);
                })
                .fail(function() {
                    $('#ihd-vip-switch-options').html(
                        '<p class="ihd-vip-no-options">Failed to load plans. Please try again.</p>'
                    );
                });
            });

            // Select a plan card → prepare switch + render inline checkout.
            $(document).on('click', '.ihd-vip-plan-card', function() {
                var variationId = $(this).data('variation');
                var $card       = $(this);

                // Highlight selected card.
                $('.ihd-vip-plan-card').css('border-color', '#ddd').css('background', '');
                $card.css('border-color', '#0073aa').css('background', '#f0f6fc');

                $('#ihd-vip-switch-loader').show();
                $('#ihd-vip-checkout-container').hide().html('');

                $.post(IHD_VIP.ajaxUrl, {
                    action: 'ihd_vip_prepare_switch',
                    nonce: IHD_VIP.nonce,
                    subscription_id: IHD_VIP.activeSubId,
                    variation_id: variationId
                })
                .done(function(resp) {
                    $('#ihd-vip-switch-loader').hide();
                    if (resp.success && resp.data.checkout_html) {
                        $('#ihd-vip-checkout-container').html(resp.data.checkout_html).show();
                        $(document.body).trigger('init_checkout');
                    } else {
                        alert(resp.data && resp.data.message ? resp.data.message : 'Could not prepare checkout.');
                    }
                })
                .fail(function() {
                    $('#ihd-vip-switch-loader').hide();
                    alert('Request failed. Please try again.');
                });
            });

        });
        </script>
        <?php
    }
}
