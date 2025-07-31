<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReviewSuccess {

    public static function init() {
        $self = new self();
        add_shortcode( 'review_success', array( $self, 'review_success_shortcode' ) );
    }

    /**
     * Shortcode:
     * [review_success partner_title="Your Stylist" partner_url="https://example.com/stylist/jane-doe"]
     */
    public function review_success_shortcode( $atts = array() ) {
        $atts = shortcode_atts(
            array(
                'partner_title' => 'this Stylist',
                'partner_url'   => home_url('/'),
            ),
            $atts,
            'review_success'
        );

        $partner_title = sanitize_text_field( $atts['partner_title'] );
        $partner_url   = esc_url( $atts['partner_url'] );

        // Unique key in case multiple instances are on the page
        $unique_key = 'review_success_' . uniqid();

        ob_start(); ?>

        <div class="review_success" id="<?php echo esc_attr( $unique_key ); ?>" data-partner-url="<?php echo $partner_url; ?>" data-partner-title="<?php echo esc_attr( $partner_title ); ?>">
            <div class="rs-wrap">
                <!-- Avatar / Badge -->
                <div class="rs-avatar" aria-hidden="true"></div>

                <!-- Heading -->
                <h2 class="rs-title">
                    Thanks for your review! <span class="rs-emoji" aria-hidden="true">🧑‍🎤</span>
                </h2>

                <!-- Subcopy -->
                <p class="rs-sub rs-sub-1">
                    We value your feedback, and it’ll be reviewed and published within a few days.
                </p>
                <p class="rs-sub rs-sub-2">
                    Loved Your Look? Help your stylist grow by sharing their profile! Your support means everything. <span aria-hidden="true">✨</span>
                </p>

                <!-- Share Card -->
                <div class="rs-card">
                    <div class="rs-card-row">
                        <div class="rs-card-title">Share this Stylist</div>
                        <div class="rs-actions" role="group" aria-label="Share actions">
                            <!-- WhatsApp -->
                            <a class="rs-action rs-wa" href="#" target="_blank" rel="noopener" aria-label="Share on WhatsApp">
                                <i class="fab fa-whatsapp" aria-hidden="true"></i>
                            </a>
                            <!-- Messenger -->
                            <a class="rs-action rs-msgr" href="#" target="_blank" rel="noopener" aria-label="Share on Messenger">
                                <i class="fab fa-facebook-messenger" aria-hidden="true"></i>
                            </a>
                            <!-- Placeholder actions (disabled) -->
                            <button class="rs-action rs-placeholder" type="button" aria-disabled="true"></button>
                            <button class="rs-action rs-placeholder" type="button" aria-disabled="true"></button>
                            <button class="rs-action rs-placeholder" type="button" aria-disabled="true"></button>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                /* ==============================
                   Review Success (scoped styles)
                   ============================== */
                .review_success {
                    --rs-bg: #ffffff;
                    --rs-text: #1a1a1a;
                    --rs-muted: #6b6b6b;
                    --rs-soft: #f3f4f6;   /* light card background */
                    --rs-ring: rgba(0,0,0,0.06);
                    --rs-border: rgba(0,0,0,0.08);
                    --rs-icon: #9aa3aa;

                    color: var(--rs-text);
                    font-family: inherit;
                }

                .review_success .rs-wrap {
                    position: relative;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    text-align: center;
                    padding: 16px 16px 8px;
                }

                /* Top-right close is handled by your modal (X), so none here */

                /* Avatar circle */
                .review_success .rs-avatar {
                    width: 88px;
                    height: 88px;
                    border-radius: 999px;
                    background: radial-gradient(circle at 40% 35%, #d9d9d9 0%, #cfcfcf 40%, #c7c7c7 60%, #bfbfbf 100%);
                    box-shadow:
                        inset 0 0 0 2px var(--rs-border),
                        0 1px 2px var(--rs-ring);
                    margin-bottom: 18px;
                }

                /* Title */
                .review_success .rs-title {
                    font-weight: 700;
                    line-height: 1.15;
                    font-size: clamp(22px, 3.3vw, 28px);
                    margin: 0 0 10px 0;
                    letter-spacing: -0.02em;
                }
                .review_success .rs-emoji { margin-left: 4px; }

                /* Subcopy */
                .review_success .rs-sub {
                    margin: 0;
                    color: var(--rs-muted);
                    font-size: clamp(14px, 2.2vw, 16px);
                }
                .review_success .rs-sub-1 { margin-bottom: 8px; }
                .review_success .rs-sub-2 { margin-bottom: 22px; }

                /* Share card */
                .review_success .rs-card {
                    width: 100%;
                    background: var(--rs-soft);
                    border-radius: 14px;
                    padding: 14px;
                    box-shadow: 0 1px 2px var(--rs-ring);
                }

                .review_success .rs-card-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                }

                .review_success .rs-card-title {
                    font-weight: 600;
                    font-size: clamp(16px, 2.4vw, 18px);
                }

                .review_success .rs-actions {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-left: auto;
                }

                .review_success .rs-action {
                    width: 36px;
                    height: 36px;
                    border-radius: 999px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    background: #fff;
                    border: 1px solid var(--rs-border);
                    box-shadow: 0 1px 1.5px var(--rs-ring);
                    transition: transform 0.12s ease, box-shadow 0.12s ease;
                }
                .review_success .rs-action:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 3px 8px rgba(0,0,0,0.08);
                }
                .review_success .rs-action i {
                    font-size: 18px;
                    color: var(--rs-icon);
                }

                /* Brand-specific tweaks (optional) */
                .review_success .rs-wa i { color: #25D366; }
                .review_success .rs-msgr i { color: #0084FF; }

                /* Placeholders (muted circles) */
                .review_success .rs-placeholder {
                    background: #e9ecef;
                    border-color: #e1e5ea;
                    cursor: default;
                    pointer-events: none;
                }

                /* Responsive */
                @media (max-width: 480px) {
                    .review_success .rs-actions {
                        margin-left: 0;
                    }
                    .review_success .rs-card-row {
                        justify-content: center;
                        text-align: center;
                    }
                }
            </style>

            <script>
                (function(){
                    // Ensure Font Awesome is available; otherwise, degrade gracefully (no JS needed)
                    var root = document.getElementById('<?php echo esc_js( $unique_key ); ?>');
                    if (!root) return;

                    var url   = root.getAttribute('data-partner-url') || window.location.href;
                    var title = root.getAttribute('data-partner-title') || 'this Stylist';

                    // Build share messages
                    var encodedURL   = encodeURIComponent(url);
                    var encodedTitle = encodeURIComponent('Check out ' + title + ': ' + url);

                    // WhatsApp
                    var wa = root.querySelector('.rs-wa');
                    if (wa) {
                        // Use wa.me on mobile; WhatsApp Web also supports it
                        wa.href = 'https://wa.me/?text=' + encodedTitle;
                    }

                    // Messenger
                    var msgr = root.querySelector('.rs-msgr');
                    if (msgr) {
                        // Messenger share endpoint
                        msgr.href = 'https://www.facebook.com/dialog/send?link=' + encodedURL +
                                    '&app_id=187904994608&redirect_uri=' + encodeURIComponent(window.location.href);
                        // If you have your own FB App ID, replace the app_id above.
                    }
                })();
            </script>
        </div>
        <?php
        return ob_get_clean();
    }
}
