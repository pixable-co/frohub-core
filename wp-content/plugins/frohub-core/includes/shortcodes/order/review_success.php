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

        $unique_key = 'review_success_' . uniqid();

        ob_start(); ?>

        <div class="review_success" id="<?php echo esc_attr( $unique_key ); ?>" data-partner-url="<?php echo $partner_url; ?>" data-partner-title="<?php echo esc_attr( $partner_title ); ?>">
            <div class="rs-wrap">
                <h2 class="rs-title">
                    Thanks for your review! <span class="rs-emoji favourite-button active" aria-hidden="true"><i class="fa-heart far"></i></span>
                </h2>

                <p class="rs-sub rs-sub-1">
                    We value your feedback, and it’ll be reviewed and published within a few days.
                </p>
                <p class="rs-sub rs-sub-2">
                    Loved Your Look? Help your stylist grow by sharing their profile! Your support means everything. <span aria-hidden="true">✨</span>
                </p>

                <div class="rs-card">
                    <div class="rs-card-row">
                        <div class="rs-card-title">Share this Stylist</div>
                        <div class="rs-actions" role="group" aria-label="Share actions">
                            <a href="https://wa.me/?text=Hey%2C+I%E2%80%99ve+been+using+FroHub+to+book+Afro+hairdressers%2C+and+it%E2%80%99s+amazing!+I+think+you%E2%80%99d+be+a+great+addition+to+their+community.+Check+it+out+if+you%E2%80%99d+like+to+join%3A+https%3A%2F%2Ffrohub.com%2Fpartners%2F+x+%F0%9F%98%8A%E2%9D%A4%EF%B8%8F" href="#" target="_blank" rel="noopener" aria-label="Share on WhatsApp">
                                <i class="fab fa-whatsapp" aria-hidden="true"></i>
                            </a>
                            <a href="https://www.messenger.com" target="_blank" href="#" target="_blank" rel="noopener" aria-label="Share on Messenger">
                                <i class="fab fa-facebook-messenger" aria-hidden="true"></i>
                            </a>
                            <a href="mailto:?subject=Join+Our+Community&body=Hey%2C+I%E2%80%99ve+been+using+FroHub+to+book+Afro+hairdressers%2C+and+it%E2%80%99s+amazing!+I+think+you%E2%80%99d+be+a+great+addition+to+their+community.+Check+it+out+if+you%E2%80%99d+like+to+join%3A+https%3A%2F%2Ffrohub.com%2Fpartners%2F+x+%F0%9F%98%8A%E2%9D%A4%EF%B8%8F" target="_blank"><i class="fas fa-envelope"></i></a>
                            <a href="sms:?body=Hey%2C+I%E2%80%99ve+been+using+FroHub+to+book+Afro+hairdressers%2C+and+it%E2%80%99s+amazing!+I+think+you%E2%80%99d+be+a+great+addition+to+their+community.+Check+it+out+if+you%E2%80%99d+like+to+join%3A+https%3A%2F%2Ffrohub.com%2Fpartners%2F+x+%F0%9F%98%8A%E2%9D%A4%EF%B8%8F" target="_blank"><i class="fas fa-sms"></i></a>
                            <button class="copy-link-btn" type="button"><i class="fas fa-link"></i><span class="copied-msg" style="display: none;">Copied</span></button>
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
                    --rs-soft: #f3f4f6;
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
                    width: 100%;
                    max-width: 520px;
                    margin: 0 auto;
                    box-sizing: border-box;
                }

                .review_success .rs-title {
                    font-weight: 700;
                    line-height: 1.2;
                    font-size: clamp(22px, 3.3vw, 28px);
                    margin: 0 0 12px 0;
                    letter-spacing: -0.02em;
                }
                .review_success .rs-emoji { margin-left: 4px; }

                .review_success .rs-sub {
                    margin: 0;
                    color: var(--rs-muted);
                    font-size: clamp(14px, 2.3vw, 16px);
                    line-height: 1.65;
                }
                .review_success .rs-sub-1 { margin-bottom: 10px; }
                .review_success .rs-sub-2 { margin-bottom: 22px; }

                .review_success .rs-card {
                    width: 100%;
                    background: var(--rs-soft);
                    border-radius: 16px;
                    padding: 14px;
                    box-shadow: 0 1px 2px var(--rs-ring);
                    overflow: hidden;      /* guard against tiny overflow */
                    box-sizing: border-box;
                    margin-top: 1rem;
                }

                .review_success .rs-card-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;       /* allow wrap when tight */
                }

                .review_success .rs-card-title {
                    font-weight: 600;
                    font-size: clamp(16px, 2.6vw, 18px);
                }

                .review_success .rs-actions {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-left: auto;
                    flex-wrap: nowrap;
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
                    flex: 0 0 auto;
                }
                .review_success .rs-action:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 3px 8px rgba(0,0,0,0.08);
                }
                .review_success .rs-action i {
                    font-size: 18px;
                    color: var(--rs-icon);
                }

                .review_success .rs-wa i { color: #25D366; }
                .review_success .rs-msgr i { color: #0084FF; }

                .review_success .rs-placeholder {
                    background: #e9ecef;
                    border-color: #e1e5ea;
                    cursor: default;
                    pointer-events: none;
                }

                /* ===== Mobile & narrow modal adjustments ===== */
                @media (max-width: 768px) {
                    .review_success .rs-wrap {
                        max-width: 440px;
                        padding: 18px 20px 10px;
                    }

                    .review_success .rs-card-row {
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        gap: 12px;
                        text-align: center;
                    }

                    .review_success .rs-actions {
                        margin-left: 0;          /* stop pushing to the edge */
                        flex-basis: 100%;        /* own line below the title */
                        justify-content: center;
                        gap: 12px;
                        flex-wrap: wrap;
                    }

                    .review_success .rs-action {
                        width: 44px;
                        height: 44px;
                    }
                    .review_success .rs-action i {
                        font-size: 20px;
                    }
                }

                @media (max-width: 360px) {
                    .review_success .rs-actions { gap: 10px; }
                    .review_success .rs-action { width: 40px; height: 40px; }
                    .review_success .rs-action i { font-size: 18px; }
                }
            </style>

            <script>
                (function(){
                    var root = document.getElementById('<?php echo esc_js( $unique_key ); ?>');
                    if (!root) return;

                    var url   = root.getAttribute('data-partner-url') || window.location.href;
                    var title = root.getAttribute('data-partner-title') || 'this Stylist';

                    var encodedURL   = encodeURIComponent(url);
                    var encodedTitle = encodeURIComponent('Check out ' + title + ': ' + url);

                    var wa = root.querySelector('.rs-wa');
                    if (wa) {
                        wa.href = 'https://wa.me/?text=' + encodedTitle;
                    }

                    var msgr = root.querySelector('.rs-msgr');
                    if (msgr) {
                        msgr.href = 'https://www.facebook.com/dialog/send?link=' + encodedURL +
                                    '&app_id=187904994608&redirect_uri=' + encodeURIComponent(window.location.href);
                    }
                })();
            </script>
        </div>
        <?php
        return ob_get_clean();
    }
}
