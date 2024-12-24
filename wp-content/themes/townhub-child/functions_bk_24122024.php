<?php
session_start();
/**
* @package TownHub - Directory & Listing WordPress Theme
* @author CTHthemes - http://themeforest.net/user/cththemes
* @date 06-11-2019
* @since 1.0.0
* @version 1.0.0
* @copyright Copyright ( C ) 2014 - 2019 cththemes.com . All rights reserved.
* @license GNU General Public License version 3 or later; see LICENSE
*/
// Your php code goes here
//
function townhub_child_enqueue_styles() {
    $parent_style = 'townhub-style';
    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css', array( 'townhub-fonts', 'townhub-plugins' ), null );
    wp_enqueue_style( 'townhub-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( $parent_style , 'townhub-color'),
        wp_get_theme()->get('Version')
    );
}
add_action( 'wp_enqueue_scripts', 'townhub_child_enqueue_styles' );

/// Adds pages within the dashbaord wrapper to use the side bars. Note: You will still need to add the menu int he sidebar.php override file.

add_filter( 'cth_listing_dashboard_skip_content', function($dashboard){
    if( $dashboard == 'affiliate-area' )
        return false;
    return $dashboard;
} );
add_action( 'cth_listing_dashboard_content_switch', function($dashboard){
    if( $dashboard == 'affiliate-area' ) {
echo get_the_content(null, false, 9903); // 9903 is your page id
}
} );


//My-Account

add_filter( 'cth_listing_dashboard_skip_content', function($dashboard){
    if( $dashboard == 'my-account' )
        return false;
    return $dashboard;
} );
add_action( 'cth_listing_dashboard_content_switch', function($dashboard){
    if( $dashboard == 'my-account' ) {
echo get_the_content(null, false, 25); // 25 is your page id
}
} );


// New code by explore logics restrict plans
add_action('woocommerce_add_to_cart_validation', 'restrict_subscription_plan_purchases', 10, 2);
function restrict_subscription_plan_purchases($passed, $product_id) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return $passed;
    }

    $free_plan_id = 2317;
    $monthly_plan_id = 2319;
    $yearly_plan_id = 2320;

    $args = [
        'customer' => $user_id,
        'status'   => ['completed'],
        'limit'    => -1,
        'orderby'  => 'date',
        'order'    => 'DESC',
    ];

    $orders = wc_get_orders($args);
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $membership_id = $order->get_id();
            $_SESSION['membership_id'] = $membership_id;
            $product_name = $item->get_name();
            $order_product_id = $item->get_product_id();
            if ($order_product_id == $free_plan_id) {
                $_SESSION['has_membership'] = true;
                $_SESSION['notice_msg'] = 'You already have an active membership. Please cancel it first to buy a new plan.';
                wp_safe_redirect(site_url('/pricing-tables/'));
                exit;
            }
            if ($order_product_id == $monthly_plan_id) {
                $_SESSION['has_membership'] = true;
                $_SESSION['notice_msg'] = 'You have already an active membership. Please cancel that first to upgrade.';
                wp_safe_redirect(site_url('/pricing-tables/'));
                exit;
            }
            if ($order_product_id == $yearly_plan_id) {
                $_SESSION['has_membership'] = true;
                $_SESSION['notice_msg'] = 'You already have an active membership. Please cancel that first to downgrade.';
                wp_safe_redirect(site_url('/pricing-tables/'));
                exit;
            }
        }
    }
    return $passed;
}

add_action('init', 'handle_cancel_membership_request');
function handle_cancel_membership_request() {
    if (isset($_POST['cancel_membership'])) {
        unset ($_SESSION['membership_id']);
        $_SESSION['membership_cancelled'] = true;
        $order_id = intval($_POST['cancel_membership_order_id']);
        $order = wc_get_order($order_id);
        if ($order && $order->get_status() === 'completed') {
            $order->update_status('cancelled', 'Membership cancelled by the user.');
        } else {
            wc_add_notice('Unable to cancel the membership. Please try again.', 'error');
        }
        wp_safe_redirect(site_url('/pricing-tables/'));
        exit;
    }
}

add_action('wp_footer', 'custom_display_pop_up_notice');
function custom_display_pop_up_notice() {
    ?>
    <style>
        #custom_popup_notice {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 20px;
            border-radius: 10px;
            z-index: 9999;
        }
        .popup-content {
            text-align: center;
        }
        .popup-content p {
            color: white !important;
        }
        #custom_popup_notice button {
            background-color: #007cba;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var popup = document.getElementById("custom_popup_notice");
            popup.style.display = "block";
        });
        function closePopup() {
            var popup = document.getElementById("custom_popup_notice");
            popup.style.display = "none";
        }
    </script>
    <?php if (isset($_SESSION['has_membership']) && $_SESSION['has_membership']) {
        unset($_SESSION['has_membership']);
        ?>
        <div id="custom_popup_notice" style="display:none;">
            <div class="popup-content">
                <p>
                    <?php
                    echo $_SESSION['notice_msg'];
                    unset ($_SESSION['notice_msg']);
                    ?>
                </p>
                <form method="post">
                    <input type="hidden" name="cancel_membership_order_id" value="<?php echo $_SESSION['membership_id']; ?>">
                    <button type="submit" name="cancel_membership" class="button">Cancel Membership</button>
                    <button onclick="closePopup()" type="button">Close</button>
                </form>
            </div>
        </div>
    <?php } elseif (isset($_SESSION['membership_cancelled'])) {
        unset($_SESSION['membership_cancelled']);
        ?>
        <div id="custom_popup_notice">
            <div class="popup-content">
                <p>Your membership has been cancelled.</p>
                <button onclick="closePopup()" type="button">Close</button>
            </div>
        </div>
    <?php }
}

