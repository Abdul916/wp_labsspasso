<?php
/* add_ons_php */

defined('ABSPATH') || exit;

class Esb_Class_Emails
{
    public static function init()
    {
        add_action('townhub_addons_insert_listing_after', array(__CLASS__, 'insert_listing_after'), 10, 2);
        add_action('townhub_addons_insert_order_after', array(__CLASS__, 'insert_order_after'), 10, 3);
        add_action('townhub_addons_order_completed', array(__CLASS__, 'order_completed'));
        add_action('townhub_addons_cthclaim_approved', array(__CLASS__, 'cthclaim_approved'), 10, 3);
        add_action('townhub_addons_lclaim_change_status_to_decline', array(__CLASS__, 'lclaim_change_status_to_decline'), 10, 1);
        add_action('townhub_addons_new_invoice', array(__CLASS__, 'new_invoice'));
        add_action('townhub_addons_booking_request_after', array(__CLASS__, 'insert_booking_after'));
        add_action('esb_insert_booking_after', array(__CLASS__, 'insert_booking_after'));
        add_action('townhub_addons_booking_submit_after', array(__CLASS__, 'insert_booking_after'));

        
        add_action('townhub_addons_edit_booking_approved', array(__CLASS__, 'edit_booking_approved'));
        add_action('townhub_addons_lclaim_change_status_to_asked_charge', array(__CLASS__, 'lclaim_change_status_to_asked_charge'), 10, 1);

        add_action('townhub_addons_booking_canceled', array(__CLASS__, 'booking_canceled'));

        

        add_action('cth_chat_reply_after', array(__CLASS__, 'chat_reply_email'), 10, 1);

        add_action('townhub_addons_insert_message_after', array(__CLASS__, 'author_message_to_email'), 10, 2);
        add_action('cth_insert_claim_listing_after', array(__CLASS__, 'insert_claim_after'), 10, 2);

        add_action('cth_insert_withdrawal_new', array(__CLASS__, 'insert_withdrawal'), 10, 2);
        add_action('cth_edit_withdrawal_approved', array(__CLASS__, 'edit_withdrawal_approved'));
        add_action('cth_insert_report_listing_after', array(__CLASS__, 'insert_report_after'), 10, 2);
        add_action('esb_addons_subscription_will_expire', array(__CLASS__, 'sub_will_expire'), 10, 2);
        

    }
    public static function wp_mail_from_name($name)
    {
        return townhub_addons_get_option('emails_name') ? townhub_addons_get_option('emails_name') : $name;
    }
    public static function wp_mail_from($email)
    {
        return townhub_addons_get_option('emails_email') ? townhub_addons_get_option('emails_email') : $email;
    }
    public static function do_wp_mail($to, $subject = '', $message = '', $headers = array(), $attachments = array())
    {
        if (townhub_addons_get_option('emails_ctype') == 'html') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }
        //$headers[] = 'From: '. $sender_option.' ' . '<'.$sender_email_option.'>';
        add_filter('wp_mail_from_name', array(__CLASS__, 'wp_mail_from_name'));
        add_filter('wp_mail_from', array(__CLASS__, 'wp_mail_from'));
        // $headers[] = 'Reply-To: '.self::wp_mail_from_name(__( 'Sender Name', 'townhub-add-ons' )) .' ' . '<'.self::wp_mail_from(__( 'senderemail@gmail.com', 'townhub-add-ons' )).'>';

        $email_sent = wp_mail($to, $subject, $message, $headers, $attachments);

        remove_filter('wp_mail_from_name', array(__CLASS__, 'wp_mail_from_name'));
        remove_filter('wp_mail_from', array(__CLASS__, 'wp_mail_from'));

        return $email_sent;
    }
    public static function process_email_template($email_template = '', $email_vars = array())
    {
        $email_vars = array_merge($email_vars, array('site_title' => get_bloginfo('name')));
        // get allow variables
        $allow_field_names = array_keys($email_vars);
        // extract variables, skip if existing
        extract($email_vars, EXTR_SKIP);
        if (preg_match_all("/{([\w\-_]+)[^\w\-_]*}/", $email_template, $matches) != false) {
            $fieldsPattern = array(); //$matches[0];
            $fieldsReplace = array();
            foreach ($matches[1] as $key => $fn) {
                $fieldsPattern[] = "/{(" . $fn . ")[^\w\-_]*}/";
                if (isset($$fn) && in_array($fn, $allow_field_names)) {
                    $fieldsReplace[] = $$fn; //'['.$fn.']';
                } else {
                    $fieldsReplace[] = '{' . $fn . '}';
                }
            }
            $email_template = preg_replace($fieldsPattern, $fieldsReplace, $email_template);
        }
        return $email_template;
    }
    public static function luser_email($userObj){
        if( !$userObj ) return '';
        $contact_email = get_user_meta( $userObj->ID, ESB_META_PREFIX.'email', true );
        if( empty($contact_email) ){
            return $userObj->user_email;
        }
        return $contact_email;
    }
    public static function insert_listing_after($listing_id = 0, $is_editing_listing = true)
    {
        if ($is_editing_listing == false) {
            $listing_post = get_post($listing_id);
            if (null != $listing_post) {
                $cats  = array();
                $terms = get_the_terms($listing_post, 'listing_cat');
                if ($terms && !is_wp_error($terms)) {

                    foreach ($terms as $term) {
                        $cats[] = $term->name;
                    }
                }
                $current_user = wp_get_current_user();

                // send admin notification email
                $email_recipients = townhub_addons_get_option('emails_admin_new_listing_recipients') ? townhub_addons_get_option('emails_admin_new_listing_recipients') : get_bloginfo('admin_email');
                if (townhub_addons_get_option('emails_admin_new_listing_enable') == 'yes') {

                    $subj_args = array(
                        'listing_number' => $listing_post->ID,
                        'listing_title'  => $listing_post->post_title,
                        'listing_date'   => Esb_Class_Date::i18n($listing_post->post_date),
                    );
                    // $email_subject = townhub_addons_process_email_template(townhub_addons_get_option('emails_admin_new_listing_subject'), $subj_args);
                    $email_subject = self::process_email_template(townhub_addons_get_option('emails_admin_new_listing_subject'), $subj_args);
                    $temp_args     = array(
                        'listing_number'   => $listing_post->ID,
                        'listing_author'   => $current_user->display_name,
                        'listing_title'    => $listing_post->post_title,
                        'listing_category' => implode(", ", $cats),
                        'listing_excerpt'  => get_the_excerpt($listing_post->ID),
                        'listing_date'     => Esb_Class_Date::i18n($listing_post->post_date),
                        'author_email'     => $current_user->user_email,

                    );
                    
                    $email_template = self::process_email_template(townhub_addons_get_option('emails_admin_new_listing_temp'), $temp_args);

                    $headers = array('Reply-To: ' . $current_user->display_name . ' ' . '<' . $current_user->user_email . '>');
                    self::do_wp_mail($email_recipients, $email_subject, $email_template, $headers);
                }
                // end new listing author email
                // send listing author email
                if (townhub_addons_get_option('emails_auth_new_listing_enable') == 'yes') {

                    $subj_args = array(
                        'listing_title' => $listing_post->post_title,
                    );
                    
                    $email_subject = self::process_email_template(townhub_addons_get_option('emails_auth_new_listing_subject'), $temp_args);
                    $temp_args      = array(
                        'listing_number'    => $listing_post->ID,
                        'listing_author'    => $current_user->display_name,
                        'listing_title'     => $listing_post->post_title,
                        'listing_category'  => implode(", ", $cats),
                        'listing_excerpt' => get_the_excerpt($listing_post->ID),
                        'listing_dashboard' => get_permalink(esb_addons_get_wpml_option('dashboard_page')),
                        'listing_date'     => Esb_Class_Date::i18n($listing_post->post_date),
                    );
                    
                    $email_template = self::process_email_template(townhub_addons_get_option('emails_auth_new_listing_temp'), $temp_args);

                    $auth_replies = array();
                    foreach ((array) $email_recipients as $em) {
                        $auth_replies[] = '<' . $em . '>';
                    }

                    $headers = array('Reply-To: ' . implode(',', $auth_replies));

                    self::do_wp_mail($current_user->user_email, $email_subject, $email_template, $headers);
                }
                // end new listing author email

            }
            // if is correct listing
        }
        // send email for submit new listing only
    }
    public static function insert_order_after($order_id = 0, $plan_id = 0, $listing_id = 0 )
    {
        if (is_numeric($order_id) && (int) $order_id > 0) {
            $order_post = get_post($order_id);
            if (null != $order_post) {
                $plan_post = get_post($plan_id);
                if (null != $plan_post) {
                    // need to check if the order is for ad campaign

                    // send admin notification email
                    if (townhub_addons_get_option('emails_admin_new_order_enable') == 'yes') {

                        $subj_args = array(
                            'order_number' => $order_post->ID,
                            'order_date'   => Esb_Class_Date::i18n($order_post->post_date),
                        );
                        // $email_subject = townhub_addons_process_email_template(townhub_addons_get_option('emails_admin_new_order_subject'), $subj_args);
                        $email_subject = self::process_email_template(townhub_addons_get_option('emails_admin_new_order_subject'), $subj_args);
                        $temp_args     = array(
                            'author'         => get_post_meta($order_id, ESB_META_PREFIX . 'display_name', true),
                            'order_amount'   => townhub_addons_get_price_formated(get_post_meta($order_id, ESB_META_PREFIX . 'amount', true)),
                            'order_currency' => get_post_meta($order_id, ESB_META_PREFIX . 'currency_code', true),
                            'order_method'   => townhub_addons_payment_names(get_post_meta($order_id, ESB_META_PREFIX . 'payment_method', true)),
                            'order_title'    => $order_post->post_title,
                            'order_number'   => $order_post->ID,
                            'order_date'     => Esb_Class_Date::i18n($order_post->post_date),
                            'expiration_date'   => Esb_Class_Date::i18n( get_post_meta($order_id, ESB_META_PREFIX . 'end_date', true) ), 
                            // 'listing_title' => $listing_post->post_title,
                            // 'listing_category' => implode(", ", $cats),
                            'plan_title'     => $plan_post->post_title,
                            'notes'         => get_post_meta( $order_id, ESB_META_PREFIX.'notes', true ),
                        );
                        // $email_template = townhub_addons_process_email_template(townhub_addons_get_option('emails_admin_new_order_temp'), $temp_args);
                        $email_template   = self::process_email_template(townhub_addons_get_option('emails_admin_new_order_temp'), $temp_args);
                        $email_recipients = townhub_addons_get_option('emails_admin_new_order_recipients') ? townhub_addons_get_option('emails_admin_new_order_recipients') : get_bloginfo('admin_email');

                        self::do_wp_mail($email_recipients, $email_subject, $email_template);
                    }
                    // end new order admi email
                }
                // end if plan_post
            }
            // end if order_post
        }
    }
    public static function order_completed($order_id = 0)
    {
        if (is_numeric($order_id) && (int) $order_id > 0) {
            $order_post = get_post($order_id);
            if (null != $order_post) {
                $plan_post = get_post(get_post_meta($order_id, ESB_META_PREFIX . 'plan_id', true));
                if (null != $plan_post) {
                    $listing_author_email = get_post_meta($order_id, ESB_META_PREFIX . 'email', true);
                    // send admin notification email
                    if (townhub_addons_get_option('emails_admin_order_completed_enable') == 'yes') {

                        $subj_args = array(
                            'order_number' => $order_post->ID,
                            'order_date'   => Esb_Class_Date::i18n($order_post->post_date),
                        );
                        // $email_subject = townhub_addons_process_email_template(townhub_addons_get_option('emails_admin_order_completed_subject'), $subj_args);
                        $email_subject = self::process_email_template(townhub_addons_get_option('emails_admin_order_completed_subject'), $subj_args);
                        $temp_args     = array(
                            'author'         => get_post_meta($order_id, ESB_META_PREFIX . 'display_name', true),
                            'order_amount'   => townhub_addons_get_price_formated(get_post_meta($order_id, ESB_META_PREFIX . 'amount', true)),
                            'order_currency' => get_post_meta($order_id, ESB_META_PREFIX . 'currency_code', true),
                            'order_method'   => townhub_addons_payment_names(get_post_meta($order_id, ESB_META_PREFIX . 'payment_method', true)),
                            'order_title'    => $order_post->post_title,
                            'order_number'   => $order_post->ID,
                            'order_date'     => Esb_Class_Date::i18n($order_post->post_date),
                            'plan_title'     => $plan_post->post_title,
                            'expiration_date'   => Esb_Class_Date::i18n( get_post_meta($order_id, ESB_META_PREFIX . 'end_date', true) ), 
                            // 'listing_title' => $listing_post->post_title,
                            // 'listing_category' => implode(", ", $cats),
                            'notes'         => get_post_meta( $order_id, ESB_META_PREFIX.'notes', true ),
                        );
                        // $email_template = townhub_addons_process_email_template(townhub_addons_get_option('emails_admin_order_completed_temp'), $temp_args);
                        $email_template   = self::process_email_template(townhub_addons_get_option('emails_admin_order_completed_temp'), $temp_args);
                        $email_recipients = townhub_addons_get_option('emails_admin_order_completed_recipients') ? townhub_addons_get_option('emails_admin_order_completed_recipients') : get_bloginfo('admin_email');

                        self::do_wp_mail($email_recipients, $email_subject, $email_template);
                    }
                    // end new order admin email

                    // send author notification email
                    if (townhub_addons_get_option('emails_auth_order_completed_enable') == 'yes' && $listing_author_email != '') {
                        if( townhub_addons_get_option('free_auth_order_completed_disabled') == 'yes' && townhub_addons_get_option('free_lplan') == $plan_post->ID ) return;
                        $subj_args = array(
                            'order_number' => $order_post->ID,
                            'order_date'   => Esb_Class_Date::i18n($order_post->post_date),
                        );
                        // $email_subject = townhub_addons_process_email_template(townhub_addons_get_option('emails_auth_order_completed_subject'), $subj_args);
                        $email_subject = self::process_email_template(townhub_addons_get_option('emails_auth_order_completed_subject'), $subj_args);
                        $temp_args     = array(
                            'author'         => get_post_meta($order_id, ESB_META_PREFIX . 'display_name', true),
                            'order_amount'   => townhub_addons_get_price_formated(get_post_meta($order_id, ESB_META_PREFIX . 'amount', true)),
                            'order_currency' => get_post_meta($order_id, ESB_META_PREFIX . 'currency_code', true),
                            'order_method'   => townhub_addons_payment_names(get_post_meta($order_id, ESB_META_PREFIX . 'payment_method', true)),
                            'order_title'    => $order_post->post_title,
                            'order_number'   => $order_post->ID,
                            'order_date'     => Esb_Class_Date::i18n($order_post->post_date),
                            'plan_title'     => $plan_post->post_title,
                            'expiration_date'   => Esb_Class_Date::i18n( get_post_meta($order_id, ESB_META_PREFIX . 'end_date', true) ), 
                            // 'listing_title' => $listing_post->post_title,
                            // 'listing_category' => implode(", ", $cats),
                            'notes'         => get_post_meta( $order_id, ESB_META_PREFIX.'notes', true ),
                        );
                        // $email_template = townhub_addons_process_email_template(townhub_addons_get_option('emails_auth_order_completed_temp'), $temp_args);
                        $email_template = self::process_email_template(townhub_addons_get_option('emails_auth_order_completed_temp'), $temp_args);

                        self::do_wp_mail($listing_author_email, $email_subject, $email_template);
                    }
                    // end new order author email
                }
                // end if plan_post
            }
            // end if order_post
        }
    }

    public static function insert_report_after($post_id = 0, $DATAS = array()){
        $postObj = get_post($post_id);
        if (null != $postObj) {
            $listing_post = get_post(get_post_meta($post_id, ESB_META_PREFIX . 'listing_id', true));
            if (null != $listing_post) {

                // $userObject = get_userdata( get_post_meta($post_id, ESB_META_PREFIX . 'user_id', true) );
                // if( !$userObject ){
                //     return;
                // }
                // $claimed_user_email = self::luser_email($userObject);

                $temp_args     = array(
                    // 'author'            => $userObject->display_name,
                    // 'email'             => $claimed_user_email,
                    
                    'listing_id'        => $listing_post->ID,
                    'listing_title'     => $listing_post->post_title,
                    'listing_url'       => get_permalink( $listing_post ),
                    

                    'date'              => Esb_Class_Date::i18n($postObj->post_date),
                    'user_name'         => get_post_meta( $post_id, ESB_META_PREFIX.'user_name', true ),
                    'user_email'        => get_post_meta( $post_id, ESB_META_PREFIX.'user_email', true ),
                    'details'           => get_post_meta( $post_id, ESB_META_PREFIX.'report_msg', true ),
                );
                        
                // send admin notification email
                if (townhub_addons_get_option('emails_admin_new_report_enable') == 'yes') {

                    $subj_args = array(
                        'id' => $postObj->ID,
                        'date'   => Esb_Class_Date::i18n($postObj->post_date),
                    );
                    
                    $email_subject = self::process_email_template(townhub_addons_get_option('emails_admin_new_report_subject'), $subj_args);
                    
                    
                    $email_template   = self::process_email_template(townhub_addons_get_option('emails_admin_new_report_temp'), $temp_args);
                    $email_recipients = !empty( townhub_addons_get_option('emails_admin_new_report_recipients') ) ? townhub_addons_get_option('emails_admin_new_report_recipients') : get_bloginfo('admin_email');

                    self::do_wp_mail($email_recipients, $email_subject, $email_template);
                }
                // end new order admin email
            }
            // end if listing_post
        }
        // end if post object
    }
    public static function insert_claim_after( $claim_id = 0, $DATAS = array() ){
        $claim_post = get_post($claim_id);
        if (null != $claim_post) {
            $listing_post = get_post(get_post_meta($claim_id, ESB_META_PREFIX . 'listing_id', true));
            if (null != $listing_post) {

                $userObject = get_userdata( get_post_meta($claim_id, ESB_META_PREFIX . 'user_id', true) );
                if( !$userObject ){
                    return;
                }

                

                $claimed_user_email = self::luser_email($userObject);

                $temp_args     = array(
                    'author'            => $userObject->display_name,
                    'email'             => $claimed_user_email,
                    
                    'listing_id'        => $listing_post->ID,
                    'listing_title'     => $listing_post->post_title,
                    'listing_url'       => get_permalink( $listing_post ),
                    

                    'date'              => Esb_Class_Date::i18n($claim_post->post_date),
                    'details'           => get_post_meta( $claim_id, ESB_META_PREFIX.'claim_msg', true ),
                );
                        
                // send admin notification email
                if (townhub_addons_get_option('emails_admin_new_claim_enable') == 'yes') {

                    $subj_args = array(
                        'id' => $claim_post->ID,
                        'date'   => Esb_Class_Date::i18n($claim_post->post_date),
                    );
                    
                    $email_subject = self::process_email_template(townhub_addons_get_option('emails_admin_new_claim_subject'), $subj_args);
                    
                    
                    $email_template   = self::process_email_template(townhub_addons_get_option('emails_admin_new_claim_temp'), $temp_args);
                    $email_recipients = !empty( townhub_addons_get_option('emails_admin_new_claim_recipients') ) ? townhub_addons_get_option('emails_admin_new_claim_recipients') : get_bloginfo('admin_email');

                    self::do_wp_mail($email_recipients, $email_subject, $email_template);
                }
                // end new order admin email

                
                // send author notification email
                if (townhub_addons_get_option('emails_auth_new_claim_enable') == 'yes' && $claimed_user_email != '') {
                    
                    $subj_args = array(
                        'id' => $claim_post->ID,
                        'date'   => Esb_Class_Date::i18n($claim_post->post_date),
                    );
                    
                    $email_subject = self::process_email_template(townhub_addons_get_option('emails_auth_new_claim_subject'), $subj_args);
                    $email_template = self::process_email_template(townhub_addons_get_option('emails_auth_new_claim_temp'), $temp_args);

                    self::do_wp_mail($claimed_user_email, $email_subject, $email_template);
                }
                // end new order author email
            }
            // end if listing_post
        }
        // end if claim post
    }
    public static function cthclaim_approved($claim_id = 0, $listing_id = 0, $user_id = 0)
    {   
        $claim_post = get_post($claim_id);
        if (null != $claim_post) {
            $listing_id = get_post_meta($claim_id, ESB_META_PREFIX . 'listing_id', true);
            // $user_id    = get_post_meta($claim_id, ESB_META_PREFIX . 'user_id', true);

            $userObject = get_userdata( get_post_meta($claim_id, ESB_META_PREFIX . 'user_id', true) );
                if( !$userObject ){
                    return;
                }

            $listing_post = get_post($listing_id);
            // $user_info    = get_userdata($user_id);
            $claimed_user_email = self::luser_email($userObject);

            $subject_temp = townhub_addons_get_option('emails_section_claim_approved_subject');
            if(empty($subject_temp)) $subject_temp = __('Claim listing approved', 'townhub-add-ons');
            $subj_args = array(
                'id'        => $claim_post->ID,
                'date'      => Esb_Class_Date::i18n($claim_post->post_date),
            );

            $email_subject = self::process_email_template($subject_temp, $subj_args);

            $content_temp = townhub_addons_get_option('emails_section_claim_approved_temp');
            if(empty($content_temp)) $content_temp = sprintf(__('{site_title}<br>Your claimed listing <a href="%2$s">%1$s</a> is approved.<br>Thank you.', 'townhub-add-ons'), $listing_post->post_title, get_permalink($listing_post->ID));
            $temp_args     = array(
                'author'            => $userObject->display_name,
                'email'             => $claimed_user_email,
                
                'listing_id'        => $listing_post->ID,
                'listing_title'     => $listing_post->post_title,
                'listing_url'       => get_permalink( $listing_post ),
                

                'date'              => Esb_Class_Date::i18n($claim_post->post_date),
                'details'           => get_post_meta( $claim_id, ESB_META_PREFIX.'claim_msg', true ),
            );

            $email_template = self::process_email_template($content_temp, $temp_args);
            self::do_wp_mail($claimed_user_email, $email_subject, $email_template);
        }
    }
    public static function lclaim_change_status_to_decline($claim_id = 0)
    {
        $claim_post = get_post($claim_id);
        if (null != $claim_post) {
            $listing_id = get_post_meta($claim_id, ESB_META_PREFIX . 'listing_id', true);
            // $user_id    = get_post_meta($claim_id, ESB_META_PREFIX . 'user_id', true);
            $userObject = get_userdata( get_post_meta($claim_id, ESB_META_PREFIX . 'user_id', true) );
                if( !$userObject ){
                    return;
                }

            $listing_post = get_post($listing_id);
            // $user_info    = get_userdata($user_id);
            $claimed_user_email = self::luser_email($userObject);
            $subject_temp = townhub_addons_get_option('emails_section_claim_declined_subject');
            if(empty($subject_temp)) $subject_temp = __('Claim listing declined', 'townhub-add-ons');
            $subj_args = array(
                'id'        => $claim_post->ID,
                'date'      => Esb_Class_Date::i18n($claim_post->post_date),
            );
            $email_subject = self::process_email_template($subject_temp, $subj_args);

            $content_temp = townhub_addons_get_option('emails_section_claim_declined_temp');
            if(empty($content_temp)) $content_temp = sprintf(__('{site_title}<br>Your claimed listing for %s is declined.<br>Thank you.', 'townhub-add-ons'), $listing_post->post_title, get_permalink($listing_post->ID));
            $temp_args     = array(
                'author'            => $userObject->display_name,
                'email'             => $claimed_user_email,
                
                'listing_id'        => $listing_post->ID,
                'listing_title'     => $listing_post->post_title,
                'listing_url'       => get_permalink( $listing_post ),
                

                'date'              => Esb_Class_Date::i18n($claim_post->post_date),
                'details'           => get_post_meta( $claim_id, ESB_META_PREFIX.'claim_msg', true ),
            );

            $email_template = self::process_email_template($content_temp, $temp_args);

            self::do_wp_mail($claimed_user_email, $email_subject, $email_template);
        }
    }
    public static function new_invoice($invoice_id = 0)
    {
        if (is_numeric($invoice_id) && (int) $invoice_id > 0) {
            $invoice_post = get_post($invoice_id);
            if (null != $invoice_post) {

                $listing_author_email = get_post_meta($invoice_id, ESB_META_PREFIX . 'user_email', true);
                // send admin notification email
                if (townhub_addons_get_option('emails_admin_new_invoice_enable') == 'yes') {

                    $subj_args = array(
                        'number' => $invoice_post->ID,
                        'date'   => Esb_Class_Date::i18n($invoice_post->post_date),
                    );
                    // $email_subject = townhub_addons_process_email_template(townhub_addons_get_option('emails_admin_new_invoice_subject'), $subj_args);
                    $email_subject = self::process_email_template(townhub_addons_get_option('emails_admin_new_invoice_subject'), $subj_args);
                    $temp_args     = Esb_Class_Invoice_CPT::get_invoice_datas($invoice_post);
                    // $email_template = townhub_addons_process_email_template(townhub_addons_get_option('emails_admin_new_invoice_temp'), $temp_args);
                    $email_template   = self::process_email_template(townhub_addons_get_option('emails_admin_new_invoice_temp'), $temp_args);
                    $email_recipients = townhub_addons_get_option('emails_admin_new_invoice_recipients') ? townhub_addons_get_option('emails_admin_new_invoice_recipients') : get_bloginfo('admin_email');

                    self::do_wp_mail($email_recipients, $email_subject, $email_template);
                }
                // end new order admin email

                // send author notification email
                if (townhub_addons_get_option('emails_auth_new_invoice_enable') == 'yes' && $listing_author_email != '') {

                    $subj_args = array(
                        'number' => $invoice_post->ID,
                        'date'   => Esb_Class_Date::i18n($invoice_post->post_date),
                    );
                    // $email_subject = townhub_addons_process_email_template(townhub_addons_get_option('emails_auth_new_invoice_subject'), $subj_args);
                    $email_subject = self::process_email_template(townhub_addons_get_option('emails_auth_new_invoice_subject'), $subj_args);
                    $temp_args     = Esb_Class_Invoice_CPT::get_invoice_datas($invoice_post);
                    // $email_template = townhub_addons_process_email_template(townhub_addons_get_option('emails_auth_new_invoice_temp'), $temp_args);
                    $email_template = self::process_email_template(townhub_addons_get_option('emails_auth_new_invoice_temp'), $temp_args);

                    self::do_wp_mail($listing_author_email, $email_subject, $email_template);
                }
                // end new order author email

            }
            // end if invoice_post
        }
    }
    public static function insert_booking_after($booking_id = 0)
    {
        if (is_numeric($booking_id) && (int) $booking_id > 0) {
            $booking_post = get_post($booking_id);
            if (null != $booking_post) {
                $listing_id   = get_post_meta($booking_id, ESB_META_PREFIX . 'listing_id', true);
                $listing_post = get_post($listing_id);
                if (null != $listing_post) {
                    $buser_id = get_post_meta($booking_id, ESB_META_PREFIX . 'user_id', true);
                    $user_obj   = get_userdata( $buser_id );
                    if( !empty($buser_id) && $user_obj ){
                        $lb_name = $user_obj->display_name;
                        $lb_email = $user_obj->user_email;
                        $lb_phone = get_user_meta( $user_obj->ID, ESB_META_PREFIX.'phone', true);
                    }else{
                        $lb_name = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_name', true );
                        $lb_email = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_email', true );
                        $lb_phone = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_phone', true );

                    }
                    if( empty($lb_name) ) $lb_name = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_name', true );
                    if( empty($lb_phone) ) $lb_phone = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_phone', true );

                    $room_details = self::booking_rooms($booking_id);
                    $room_details_new = self::booking_rooms_new($booking_id);
                    $room_details_old = self::booking_rooms_old($booking_id);

                    $menus_details = self::booking_menus($booking_id);
                    $ticket_details = self::booking_tickets($booking_id);
                    $tour_slots_details = self::booking_tour_slots($booking_id);

                    $booking_services = self::booking_services($booking_id);

                    $lb_adults     = get_post_meta( $booking_id, ESB_META_PREFIX.'adults', true );
                    $lb_children  = get_post_meta( $booking_id, ESB_META_PREFIX.'children', true );
                    $lb_infants  = get_post_meta( $booking_id, ESB_META_PREFIX.'infants', true );
                    $person = (int)$lb_adults + (int)$lb_children + (int)$lb_infants;
                    $listing_author   = get_user_by('ID', $listing_post->post_author);
                    $email_recipients = array();
                    if ( $listing_author ) {
                        $email_recipients[] = $listing_author->user_email;
                    }

                    // also send to site owner
                    if (townhub_addons_get_option('emails_section_auth_booking_insert_admin','yes') == 'yes') $email_recipients[] = townhub_addons_get_option('admin_recipients') ? townhub_addons_get_option('admin_recipients') : get_bloginfo('admin_email');
                    // listing email
                    // $email_recipients[] = get_post_meta( $listing_id, ESB_META_PREFIX.'email', true );

                    $bktimes = get_post_meta($booking_id, ESB_META_PREFIX . 'times', true);
                    if (!empty($bktimes)) {
                        $bktimes = implode('<br \>', $bktimes);
                    }

                    $bkslots = get_post_meta($booking_id, ESB_META_PREFIX . 'time_slots', true);
                    if (!empty($bkslots)) {
                        
                        $tSlots = array();
                        foreach ($bkslots as $bkslot) {
                            $bkslot = (array)$bkslot;
                            $tSlots[] = $bkslot['title'];
                            // $slkey = array_search($bkslot, array_column($listing_slots, 'slot_id'));
                            // if( false !== $slkey ){
                            //     $tSlots[] = $listing_slots[$slkey]['time'];
                            // }
                        }
                        $bkslots = implode('<br \>', $tSlots);
                    }

                    $checkin = get_post_meta($booking_id, ESB_META_PREFIX . 'checkin', true);
                    if ($checkin != '') {
                        $checkin = Esb_Class_Date::i18n($checkin);
                    }

                    $checkout = get_post_meta($booking_id, ESB_META_PREFIX . 'checkout', true);
                    if ($checkout != '') {
                        $checkout = Esb_Class_Date::i18n($checkout);
                    }

                    $cv_pdf_id = get_post_meta($booking_id, ESB_META_PREFIX . 'cv_pdf_id', true);

                    $temp_args = array(
                        'author'        => $listing_author->display_name,
                        'name'          => $lb_name,
                        'email'         => $lb_email,
                        'phone'         => $lb_phone,
                        'day'           => get_post_meta($booking_id, ESB_META_PREFIX . 'nights', true),
                        'person'        => $person,
                        'listing_title' => $listing_post->post_title,
                        'listing_url' => get_the_permalink( $listing_post, false ),
                        'room_type'         => $room_details,
                        'rooms'             => $room_details,
                        'rooms_dates'       => $room_details_old,
                        'rooms_persons'     => $room_details_new,
                        'menus'             => $menus_details,
                        'tickets'           => $ticket_details,
                        'tour_slots'        => $tour_slots_details,

                        // olb booking form
                        'quantity'      => get_post_meta($booking_id, ESB_META_PREFIX . 'lb_quantity', true),
                        'date'          => Esb_Class_Date::i18n(get_post_meta($booking_id, ESB_META_PREFIX . 'lb_date', true)),
                        'time'          => get_post_meta($booking_id, ESB_META_PREFIX . 'lb_time', true),
                        'info'          => get_post_meta($booking_id, ESB_META_PREFIX . 'notes', true),

                        'checkin'       => $checkin,
                        'checkout'      => $checkout,

                        'times'         => $bktimes,
                        'slots'         => $bkslots,

                        'notes'          => get_post_meta($booking_id, ESB_META_PREFIX . 'notes', true),

                        'adults' => $lb_adults,
                        'children' => $lb_children,
                        'infants' => $lb_infants,
                        'booking_services'     => $booking_services,

                        'total'                 => townhub_addons_get_price_formated( get_post_meta($booking_id, ESB_META_PREFIX . 'price_total', true) ), 
                        'payment_method'    => townhub_addons_payment_names(get_post_meta( $booking_id, ESB_META_PREFIX.'payment_method', true )),

                        'billing_details'           => Esb_Class_User::billingDetails($buser_id),
                        'cv_url'                    => wp_get_attachment_url( $cv_pdf_id ),
                        'cv_name'                   => get_the_title( $cv_pdf_id ),
                    );

                    

                    $temp_args = (array) apply_filters('listing_booking_email_args', $temp_args, $booking_id, $listing_id);

                    if (townhub_addons_get_option('emails_section_auth_booking_insert_enable') == 'yes') {

                        $subj_args = array(
                            'listing_title' => $listing_post->post_title,
                            'listing_url' => get_the_permalink( $listing_post, false ),
                        );
                        // $email_subject = townhub_addons_process_email_template(townhub_addons_get_option('emails_section_auth_booking_insert_subject'), $subj_args);
                        $email_subject = self::process_email_template(townhub_addons_get_option('emails_section_auth_booking_insert_subject'), $subj_args);

                        $booking_insert_auth_temp = apply_filters('listing_booking_insert_auth_temp', townhub_addons_get_option('emails_section_auth_booking_insert_temp'), $booking_id, $listing_id);
                        $email_template = self::process_email_template($booking_insert_auth_temp, $temp_args);

                        $headers = array('Reply-To: ' . $lb_name . ' ' . '<' . $lb_email . '>');

                        self::do_wp_mail($email_recipients, $email_subject, $email_template, $headers);
                    }
                    // listing author/admin emails

                    if (townhub_addons_get_option('emails_section_customer_booking_insert_enable') == 'yes' && $lb_email != '') {

                        $subj_args = array(
                            'listing_title' => $listing_post->post_title,
                            'listing_url' => get_the_permalink( $listing_post, false ),
                        );
                        // $email_subject = townhub_addons_process_email_template(townhub_addons_get_option('emails_section_customer_booking_insert_subject'), $subj_args);
                        $email_subject = self::process_email_template(townhub_addons_get_option('emails_section_customer_booking_insert_subject'), $subj_args);

                        $booking_insert_customer_temp = apply_filters('listing_booking_insert_customer_temp', townhub_addons_get_option('emails_section_customer_booking_insert_temp'), $booking_id, $listing_id);
                        $email_template = self::process_email_template($booking_insert_customer_temp, $temp_args);

                        $auth_replies = array();
                        foreach ($email_recipients as $em) {
                            $auth_replies[] = '<' . $em . '>';
                        }

                        $headers = array('Reply-To: ' . implode(',', $auth_replies));

                        self::do_wp_mail($lb_email, $email_subject, $email_template, $headers);
                    }
                    // listing customer email
                }
                // end if is valid listing
            }
            // end if is valid booking

        }

    }
    public static function edit_booking_approved($booking_id = 0)
    {
        if (is_numeric($booking_id) && (int) $booking_id > 0) {
            $booking_post = get_post($booking_id);
            if (null != $booking_post) {
                $listing_id   = get_post_meta($booking_id, ESB_META_PREFIX . 'listing_id', true);
                $listing_post = get_post($listing_id);
                if (null != $listing_post) {
                    $buser_id = get_post_meta($booking_id, ESB_META_PREFIX . 'user_id', true);
                    $user_obj   = get_userdata( $buser_id );
                    if( !empty($buser_id) && $user_obj ){
                        $lb_name = $user_obj->display_name;
                        $lb_email = $user_obj->user_email;
                        $lb_phone = get_user_meta( $user_obj->ID, '_cth_phone', true);
                    }else{
                        $lb_name = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_name', true );
                        $lb_email = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_email', true );
                        $lb_phone = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_phone', true );

                    }
                    if( empty($lb_name) ) $lb_name = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_name', true );
                    if( empty($lb_phone) ) $lb_phone = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_phone', true );

                    
                    $room_details = self::booking_rooms($booking_id);
                    $room_details_new = self::booking_rooms_new($booking_id);
                    $room_details_old = self::booking_rooms_old($booking_id);

                    $menus_details = self::booking_menus($booking_id);
                    $ticket_details = self::booking_tickets($booking_id);
                    $tour_slots_details = self::booking_tour_slots($booking_id);

                    $booking_services = self::booking_services($booking_id);

                    $lb_adults     = get_post_meta( $booking_id, ESB_META_PREFIX.'adults', true );
                    $lb_children  = get_post_meta( $booking_id, ESB_META_PREFIX.'children', true );
                    $lb_infants  = get_post_meta( $booking_id, ESB_META_PREFIX.'infants', true );
                    $person = (int)$lb_adults + (int)$lb_children + (int)$lb_infants;
                    $listing_author = get_user_by('ID', $listing_post->post_author);

                    $email_recipients = array();
                    if ( $listing_author ) {
                        $email_recipients[] = $listing_author->user_email;
                    }

                    // also send to site owner
                    if( townhub_addons_get_option('emails_auth_booking_completed_admin','yes') == 'yes' ) $email_recipients[] = townhub_addons_get_option('admin_recipients') ? townhub_addons_get_option('admin_recipients') : get_bloginfo('admin_email');

                    $subj_args = array(
                        'listing_title' => $listing_post->post_title,
                        'listing_url' => get_the_permalink( $listing_post, false ),
                    );
                    $bktimes = get_post_meta($booking_id, ESB_META_PREFIX . 'times', true);
                    if (!empty($bktimes)) {
                        $bktimes = implode('<br \>', $bktimes);
                    }

                    $bkslots = get_post_meta($booking_id, ESB_META_PREFIX . 'time_slots', true);
                    if (!empty($bkslots)) {
                        
                        $tSlots = array();
                        foreach ($bkslots as $bkslot) {
                            $bkslot = (array)$bkslot;
                            $tSlots[] = $bkslot['title'];
                            // $slkey = array_search($bkslot, array_column($listing_slots, 'slot_id'));
                            // if( false !== $slkey ){
                            //     $tSlots[] = $listing_slots[$slkey]['time'];
                            // }
                        }
                        $bkslots = implode('<br \>', $tSlots);
                    }

                    $checkin = get_post_meta($booking_id, ESB_META_PREFIX . 'checkin', true);
                    if ($checkin != '') {
                        $checkin = Esb_Class_Date::i18n($checkin);
                    }

                    $checkout = get_post_meta($booking_id, ESB_META_PREFIX . 'checkout', true);
                    if ($checkout != '') {
                        $checkout = Esb_Class_Date::i18n($checkout);
                    }

                    $cv_pdf_id = get_post_meta($booking_id, ESB_META_PREFIX . 'cv_pdf_id', true);

                    $temp_args = array(
                        'author'        => $listing_author->display_name,
                        'name'          => $lb_name,
                        'email'         => $lb_email,
                        'phone'         => $lb_phone,
                        'day'           => get_post_meta($booking_id, ESB_META_PREFIX . 'nights', true),
                        'person'        => $person,
                        'listing_title' => $listing_post->post_title,
                        'listing_url' => get_the_permalink( $listing_post, false ),
                        'room_type'         => $room_details,
                        'rooms'             => $room_details,
                        'rooms_dates'       => $room_details_old,
                        'rooms_persons'     => $room_details_new,
                        'menus'             => $menus_details,
                        'tickets'           => $ticket_details,
                        'tour_slots'        => $tour_slots_details,


                        // olb booking form
                        'quantity'      => get_post_meta($booking_id, ESB_META_PREFIX . 'lb_quantity', true),
                        'date'          => Esb_Class_Date::i18n(get_post_meta($booking_id, ESB_META_PREFIX . 'lb_date', true)),
                        'time'          => get_post_meta($booking_id, ESB_META_PREFIX . 'lb_time', true),
                        'info'          => get_post_meta($booking_id, ESB_META_PREFIX . 'lb_add_info', true),

                        'checkin'       => $checkin,
                        'checkout'      => $checkout,

                        'times'         => $bktimes,
                        'slots'         => $bkslots,

                        'notes'          => get_post_meta($booking_id, ESB_META_PREFIX . 'notes', true),

                        'adults' => $lb_adults,
                        'children' => $lb_children,
                        'infants' => $lb_infants,

                        'booking_services'      => $booking_services,
                        'total'                 => townhub_addons_get_price_formated( get_post_meta($booking_id, ESB_META_PREFIX . 'price_total', true) ), 
                        'payment_method'    => townhub_addons_payment_names(get_post_meta( $booking_id, ESB_META_PREFIX.'payment_method', true )),
                        'billing_details'           => Esb_Class_User::billingDetails($buser_id),
                        'cv_url'                    => wp_get_attachment_url( $cv_pdf_id ),
                        'cv_name'                   => get_the_title( $cv_pdf_id ),
                    );

                    if (townhub_addons_get_option('emails_auth_booking_completed_enable') == 'yes' ) {
                        $email_subject = self::process_email_template(townhub_addons_get_option('emails_auth_booking_completed_subject'), $subj_args);
                        $temp_args = (array) apply_filters('listing_booking_approved_email_auth_args', $temp_args, $booking_id, $listing_id);
                        $booking_approved_temp = apply_filters('listing_booking_approved_email_auth_temp', townhub_addons_get_option('emails_auth_booking_completed_temp'), $booking_id, $listing_id);
                        $email_template = self::process_email_template($booking_approved_temp, $temp_args);
                        // $headers = array('Reply-To: ' . $lb_name . ' ' . '<' . $lb_email . '>');
                        $headers = array();
                        self::do_wp_mail($email_recipients, $email_subject, $email_template, $headers);
                    }
                    

                    if (townhub_addons_get_option('emails_section_customer_booking_approved_enable') == 'yes' && $lb_email != '') {
                        $email_subject = self::process_email_template(townhub_addons_get_option('emails_section_customer_booking_approved_subject'), $subj_args);
                        $temp_args = (array) apply_filters('listing_booking_approved_email_args', $temp_args, $booking_id, $listing_id);
                        $booking_approved_temp = apply_filters('listing_booking_approved_email_temp', townhub_addons_get_option('emails_section_customer_booking_approved_temp'), $booking_id, $listing_id);
                        $email_template = self::process_email_template($booking_approved_temp, $temp_args);

                        // $headers = array('Reply-To: ' . $listing_author->display_name . ' ' . '<' . $listing_author->user_email . '>');
                        $auth_replies = array();
                        foreach ($email_recipients as $em) {
                            $auth_replies[] = '<' . $em . '>';
                        }

                        $headers = array('Reply-To: ' . implode(',', $auth_replies));
                        self::do_wp_mail($lb_email, $email_subject, $email_template, $headers);
                    }
                    // listing customer email
                }
                // end if is valid listing
            }
            // end if is valid booking

        }

    }
    public static function booking_canceled($booking_id = 0)
    {
        if (is_numeric($booking_id) && (int) $booking_id > 0) {
            $booking_post = get_post($booking_id);
            if (null != $booking_post) {
                $listing_id   = get_post_meta($booking_id, ESB_META_PREFIX . 'listing_id', true);
                $listing_post = get_post($listing_id);
                if (null != $listing_post) {
                    $buser_id = get_post_meta($booking_id, ESB_META_PREFIX . 'user_id', true);
                    $user_obj   = get_userdata( $buser_id );
                    if( !empty($buser_id) && $user_obj ){
                        $lb_name = $user_obj->display_name;
                        $lb_email = $user_obj->user_email;
                        $lb_phone = get_user_meta( $user_obj->ID, ESB_META_PREFIX.'phone', true);
                    }else{
                        $lb_name = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_name', true );
                        $lb_email = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_email', true );
                        $lb_phone = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_phone', true );

                    }
                    if( empty($lb_name) ) $lb_name = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_name', true );
                    if( empty($lb_phone) ) $lb_phone = get_post_meta( $booking_id, ESB_META_PREFIX.'lb_phone', true );

                    $room_details = self::booking_rooms($booking_id);
                    $room_details_new = self::booking_rooms_new($booking_id);
                    $room_details_old = self::booking_rooms_old($booking_id);

                    $menus_details = self::booking_menus($booking_id);
                    $ticket_details = self::booking_tickets($booking_id);
                    $tour_slots_details = self::booking_tour_slots($booking_id);

                    $booking_services = self::booking_services($booking_id);

                    $lb_adults     = get_post_meta( $booking_id, ESB_META_PREFIX.'adults', true );
                    $lb_children  = get_post_meta( $booking_id, ESB_META_PREFIX.'children', true );
                    $lb_infants  = get_post_meta( $booking_id, ESB_META_PREFIX.'infants', true );
                    $person = (int)$lb_adults + (int)$lb_children + (int)$lb_infants;
                    $listing_author   = get_user_by('ID', $listing_post->post_author);
                    $email_recipients = array();
                    if ( $listing_author ) {
                        $email_recipients[] = $listing_author->user_email;
                    }

                    // also send to site owner
                    if( townhub_addons_get_option('emails_section_auth_booking_canceled_admin','yes') == 'yes' ) $email_recipients[] = townhub_addons_get_option('admin_recipients') ? townhub_addons_get_option('admin_recipients') : get_bloginfo('admin_email');
                    // listing email
                    // $email_recipients[] = get_post_meta( $listing_id, ESB_META_PREFIX.'email', true );

                    $bktimes = get_post_meta($booking_id, ESB_META_PREFIX . 'times', true);
                    if (!empty($bktimes)) {
                        $bktimes = implode('<br \>', $bktimes);
                    }

                    $bkslots = get_post_meta($booking_id, ESB_META_PREFIX . 'time_slots', true);
                    if (!empty($bkslots)) {
                        
                        $tSlots = array();
                        foreach ($bkslots as $bkslot) {
                            $bkslot = (array)$bkslot;
                            $tSlots[] = $bkslot['title'];
                            // $slkey = array_search($bkslot, array_column($listing_slots, 'slot_id'));
                            // if( false !== $slkey ){
                            //     $tSlots[] = $listing_slots[$slkey]['time'];
                            // }
                        }
                        $bkslots = implode('<br \>', $tSlots);
                    }

                    $checkin = get_post_meta($booking_id, ESB_META_PREFIX . 'checkin', true);
                    if ($checkin != '') {
                        $checkin = Esb_Class_Date::i18n($checkin);
                    }

                    $checkout = get_post_meta($booking_id, ESB_META_PREFIX . 'checkout', true);
                    if ($checkout != '') {
                        $checkout = Esb_Class_Date::i18n($checkout);
                    }

                    $cv_pdf_id = get_post_meta($booking_id, ESB_META_PREFIX . 'cv_pdf_id', true);

                    $temp_args = array(
                        'author'        => $listing_author->display_name,
                        'name'          => $lb_name,
                        'email'         => $lb_email,
                        'phone'         => $lb_phone,
                        'day'           => get_post_meta($booking_id, ESB_META_PREFIX . 'nights', true),
                        'person'        => $person,
                        'listing_title' => $listing_post->post_title,
                        'listing_url' => get_the_permalink( $listing_post, false ),
                        'room_type'         => $room_details,
                        'rooms'             => $room_details,
                        'rooms_dates'       => $room_details_old,
                        'rooms_persons'     => $room_details_new,
                        'menus'             => $menus_details,
                        'tickets'           => $ticket_details,
                        'tour_slots'        => $tour_slots_details,

                        // olb booking form
                        'quantity'      => get_post_meta($booking_id, ESB_META_PREFIX . 'lb_quantity', true),
                        'date'          => Esb_Class_Date::i18n(get_post_meta($booking_id, ESB_META_PREFIX . 'lb_date', true)),
                        'time'          => get_post_meta($booking_id, ESB_META_PREFIX . 'lb_time', true),
                        'info'          => get_post_meta($booking_id, ESB_META_PREFIX . 'notes', true),

                        'checkin'       => $checkin,
                        'checkout'      => $checkout,

                        'times'         => $bktimes,
                        'slots'         => $bkslots,

                        'notes'          => get_post_meta($booking_id, ESB_META_PREFIX . 'notes', true),

                        'adults' => $lb_adults,
                        'children' => $lb_children,
                        'infants' => $lb_infants,
                        'booking_services'     => $booking_services,

                        'total'                 => townhub_addons_get_price_formated( get_post_meta($booking_id, ESB_META_PREFIX . 'price_total', true) ), 
                        'payment_method'    => townhub_addons_payment_names(get_post_meta( $booking_id, ESB_META_PREFIX.'payment_method', true )),

                        'cv_url'                    => wp_get_attachment_url( $cv_pdf_id ),
                        'cv_name'                   => get_the_title( $cv_pdf_id ),
                    );

                    $temp_args = (array) apply_filters('listing_booking_email_args', $temp_args, $booking_id, $listing_id);
                    $subj_args = array(
                        'listing_title' => $listing_post->post_title,
                        'listing_url' => get_the_permalink( $listing_post, false ),
                    );

                    if (townhub_addons_get_option('emails_section_auth_booking_canceled_enable') == 'yes') {

                        
                        // $email_subject = townhub_addons_process_email_template(townhub_addons_get_option('emails_section_auth_booking_insert_subject'), $subj_args);
                        $email_subject = self::process_email_template(townhub_addons_get_option('emails_section_auth_booking_canceled_subject'), $subj_args);

                        $booking_insert_auth_temp = apply_filters('listing_booking_cancel_auth_temp', townhub_addons_get_option('emails_section_auth_booking_canceled_temp'), $booking_id, $listing_id);
                        $email_template = self::process_email_template($booking_insert_auth_temp, $temp_args);

                        $headers = array('Reply-To: ' . $lb_name . ' ' . '<' . $lb_email . '>');

                        self::do_wp_mail($email_recipients, $email_subject, $email_template, $headers);
                    }
                    // listing author/admin emails

                    if (townhub_addons_get_option('emails_section_customer_booking_canceled_enable') == 'yes' && $lb_email != '') {
                        $email_subject = self::process_email_template(townhub_addons_get_option('emails_section_customer_booking_canceled_subject'), $subj_args);
                        // $temp_args = (array) apply_filters('listing_booking_cancel_email_args', $temp_args, $booking_id, $listing_id);
                        $booking_approved_temp = apply_filters('listing_booking_cancel_customer_temp', townhub_addons_get_option('emails_section_customer_booking_canceled_temp'), $booking_id, $listing_id);
                        $email_template = self::process_email_template($booking_approved_temp, $temp_args);

                        // $headers = array('Reply-To: ' . $listing_author->display_name . ' ' . '<' . $listing_author->user_email . '>');
                        $auth_replies = array();
                        foreach ($email_recipients as $em) {
                            $auth_replies[] = '<' . $em . '>';
                        }

                        $headers = array('Reply-To: ' . implode(',', $auth_replies));
                        self::do_wp_mail($lb_email, $email_subject, $email_template, $headers);
                    }
                    // listing customer email

                    

                    
                }
                // end if is valid listing
            }
            // end if is valid booking

        }

    }

    public static function booking_rooms($booking_id){
        $rooms = get_post_meta( $booking_id, ESB_META_PREFIX.'rooms', true );  
        $room_details = array();
        if( !empty($rooms) ){
            foreach ((array)$rooms as $room) {
                if(isset($room['ID']) && isset($room['quantity']) && (int)$room['quantity'] > 0){
                    $rprice = (float)get_post_meta( $room['ID'], '_price', true );
                    $rquantity = (int)$room['quantity'];
                    $room_details[] = sprintf( __( '<strong>%s</strong>: %d x %s = %s', 'townhub-add-ons' ), get_the_title( $room['ID'] ), $rquantity, townhub_addons_get_price_formated($rprice), townhub_addons_get_price_formated($rquantity*$rprice) );
                }
            }
        }
        return implode("<br>", $room_details);
    }
    public static function booking_rooms_old($booking_id){
        $rooms_persons = get_post_meta( $booking_id, ESB_META_PREFIX.'rooms_old_data', true );
        $room_details = array();
        if( !empty($rooms_persons) ){
            foreach ((array)$rooms_persons as $rdata) {

                $room_details[] = $rdata['title'];
                $roomdates = array();
                foreach ($rdata['rdates'] as $rdte => $rdval) {
                    $roomdates[] = Esb_Class_Date::i18n( $rdte );
                    if( !empty($rdata['quantity']) ) $roomdates[] = sprintf( _x( '<strong>Quantity</strong>: %d x %s', 'email room dates', 'townhub-add-ons' ), $rdata['quantity'], townhub_addons_get_price_formated( $rdval ) );
            
                }

                $room_details[] = implode("<br>", $roomdates);
                
            }
        }
        return implode("<br>", $room_details);
    }
    public static function booking_rooms_new($booking_id){
        $rooms_persons = get_post_meta( $booking_id, ESB_META_PREFIX.'rooms_person_data', true );
        $room_details = array();
        if( !empty($rooms_persons) ){
            foreach ((array)$rooms_persons as $rdata) {

                $room_details[] = $rdata['title'];
                $roomdates = array();
                foreach ($rdata['rdates'] as $rdte => $rdval) {
                    $roomdates[] = Esb_Class_Date::i18n( $rdte );
                    if( !empty($rdata['adults']) ) $roomdates[] = sprintf( _x( '<strong>Adults</strong>: %d x %s', 'email room persons', 'townhub-add-ons' ), $rdata['adults'], townhub_addons_get_price_formated($rdval['adults']) );
                    if( !empty($rdata['children']) ) $roomdates[] = sprintf( _x( '<strong>Children</strong>: %d x %s', 'email room persons', 'townhub-add-ons' ), $rdata['children'], townhub_addons_get_price_formated($rdval['children']) );
                    if( !empty($rdata['infant']) ) $roomdates[] = sprintf( _x( '<strong>Infant</strong>: %d x %s', 'email room persons', 'townhub-add-ons' ), $rdata['infant'], townhub_addons_get_price_formated($rdval['infant']) );
                }

                $room_details[] = implode("<br>", $roomdates);
                
            }
        }
        return implode("<br>", $room_details);
    }

    public static function booking_tickets($booking_id){
        $tickets = get_post_meta( $booking_id, ESB_META_PREFIX.'tickets', true );  
        $details = array();
        if( !empty($tickets) ){
            foreach ((array)$tickets as $ticket) {
                if( isset($ticket['quantity']) && (int)$ticket['quantity'] > 0){
                    
                    $price = (float)$ticket['price'];
                    $quantity = (int)$ticket['quantity'];

                    $details[] = sprintf( __( '<strong>%s</strong>: %d x %s = %s', 'townhub-add-ons' ), $ticket['title'], $quantity, townhub_addons_get_price_formated($price), townhub_addons_get_price_formated($quantity*$price) );

                    
                }
                
            }
        }
        return implode("<br>", $details);
    }
    public static function booking_menus($booking_id){
        $tickets = get_post_meta( $booking_id, ESB_META_PREFIX.'bk_menus', true );  
        $details = array();
        if( !empty($tickets) ){
            foreach ((array)$tickets as $ticket) {
                if( isset($ticket['quantity']) && (int)$ticket['quantity'] > 0){
                    
                    $price = (float)$ticket['price'];
                    $quantity = (int)$ticket['quantity'];

                    $details[] = sprintf( __( '<strong>%s</strong>: %d x %s = %s', 'townhub-add-ons' ), $ticket['title'], $quantity, townhub_addons_get_price_formated($price), townhub_addons_get_price_formated($quantity*$price) );

                    
                }
                
            }
        }
        return implode("<br>", $details);
    }
    public static function booking_services($booking_id){
        $book_services = get_post_meta( $booking_id, ESB_META_PREFIX.'book_services', true );  
        $details = array();
        if( is_array($book_services ) && !empty($book_services) ){
            foreach ( $book_services as $service) {
                if( isset($service['quantity']) && (int)$service['quantity'] > 0){
                    
                    $price = (float)$service['price'];
                    $quantity = (int)$service['quantity'];

                    $details[] = sprintf( __( '<strong>%s</strong>: %d x %s = %s', 'townhub-add-ons' ), $service['title'], $quantity, townhub_addons_get_price_formated($price), townhub_addons_get_price_formated($quantity*$price) );

                    
                }
                
            }
        }
        return implode("<br>", $details);
    }
    public static function booking_tour_slots($booking_id){
        $tour_slots = get_post_meta( $booking_id, ESB_META_PREFIX.'tour_slots', true );  
        $details = array();
        if( !empty($tour_slots) ){
            foreach ((array)$tour_slots as $ticket) {
                if( isset($ticket['adults']) && (int)$ticket['adults'] > 0){
                    
                    $price = (float)$ticket['price'];
                    $quantity = (int)$ticket['adults'];

                    $details[] = sprintf( __( '<strong>%s</strong>: Adult %d x %s = %s', 'townhub-add-ons' ), $ticket['title'], $quantity, townhub_addons_get_price_formated($price), townhub_addons_get_price_formated($quantity*$price) );

                    
                }
                if( isset($ticket['children']) && (int)$ticket['children'] > 0){
                    
                    $price = (float)$ticket['child_price'];
                    $quantity = (int)$ticket['children'];

                    $details[] = sprintf( __( '<strong>%s</strong>: Children %d x %s = %s', 'townhub-add-ons' ), $ticket['title'], $quantity, townhub_addons_get_price_formated($price), townhub_addons_get_price_formated($quantity*$price) );

                    
                }
            }
        }
        return implode("<br>", $details);
    }
    public static function lclaim_change_status_to_asked_charge($claim_id = 0)
    {
        if (is_numeric($claim_id) && (int) $claim_id > 0) {
            $claim_post = get_post($claim_id);
            if (null != $claim_post) {

                $user_info  = get_userdata(get_post_meta($claim_id, ESB_META_PREFIX . 'user_id', true));
                $listing_id = get_post_meta($claim_id, ESB_META_PREFIX . 'listing_id', true);

                $listing_author = get_userdata($claim_post->post_author);

                $subj_args = array(
                    'id'   => $claim_id,
                    'date' => current_time(get_option('date_format')),
                );
                // $email_subject = townhub_addons_process_email_template(townhub_addons_get_option('emails_auth_claim_subject'), $subj_args);
                $email_subject = self::process_email_template(townhub_addons_get_option('emails_auth_claim_subject'), $subj_args);
                $temp_args     = array(
                    'author'        => $listing_author->display_name,
                    'date'          => Esb_Class_Date::i18n($claim_post->post_date),
                    'listing_id'    => $listing_id,
                    'listing_title' => get_the_title($listing_id),
                    'listing_url'   => get_the_permalink($listing_id),
                    'add_to_cart'   => townhub_addons_get_add_to_cart_url($claim_id),
                );

                // $email_template = townhub_addons_process_email_template(townhub_addons_get_option('emails_auth_claim_temp'), $temp_args);
                $email_template = self::process_email_template(townhub_addons_get_option('emails_auth_claim_temp'), $temp_args);
                $headers = array();
                if( $listing_author->user_email != $user_info->user_email ){
                    $headers = array('Reply-To: ' . $listing_author->display_name . ' ' . '<' . $listing_author->user_email . '>');
                }
                // $headers = array('Reply-To: ' . $listing_author->display_name . ' ' . '<' . $listing_author->user_email . '>');

                self::do_wp_mail($user_info->user_email, $email_subject, $email_template, $headers);

            } // end check post object
        } // end check id

    }

    public static function chat_reply_email($reply_obj)
    {
        // get to user
        $to_user = $reply_obj['to_user'];
        // $to_user = $reply_obj['uid'];
        // if ($reply_obj['user_one'] == $reply_obj['current_user']) {
        //     $to_user = $reply_obj['user_two'];
        // }

        $receiver = get_userdata($to_user);
        $replyer  = get_userdata($reply_obj['current_user']);

        $temp_args = array(
            'receiver'   => $receiver->display_name,
            'reply_text' => $reply_obj['reply'],
            'date'       => current_time(get_option('date_format')),
            'replyer'    => $replyer->display_name,
        );
        $temp_args = (array) apply_filters('chat_reply_email_args', $temp_args, $reply_obj, $to_user);
        $email_template = self::process_email_template(
            townhub_addons_get_option('new_chat_temp'), 
            $temp_args
        );

        $headers = array('Reply-To: ' . $replyer->display_name . ' ' . '<' . $replyer->user_email . '>');
        self::do_wp_mail($receiver->user_email, 'Chat reply', $email_template, $headers);
    }

    public static function author_message_to_email($message, $datas)
    {
        $receiver = get_userdata($datas['to_user_id']);

        $temp_args = array(
            'author'  => $receiver->display_name,
            'message' => $datas['lmsg_message'],
            'date'    => current_time(get_option('date_format')),
            'name'    => $datas['lmsg_name'],
            'phone'   => $datas['lmsg_phone'],
            'email'   => $datas['lmsg_email'],
            'to_email'   => $receiver->user_email,
            'listing'           => get_the_title( $datas['listing_id'] ),
        );
        $temp_args = (array) apply_filters('author_message_email_args', $temp_args, $message, $datas, $receiver );
        $email_template = self::process_email_template(
            townhub_addons_get_option('new_auth_msg_temp'), 
            $temp_args
        );

        $headers = array('Reply-To: ' . $datas['lmsg_name'] . ' ' . '<' . $datas['lmsg_email'] . '>');
        $subject = __('New Message: Potential Supervisee', 'townhub-add-ons');
        self::do_wp_mail($receiver->user_email, $subject, $email_template, $headers);
    }

    public static function insert_withdrawal($post_id = 0, $author_id = 0)
    {
        if ( !empty($post_id) ) {
            
            $postObj = get_post($post_id);
            $userObj = get_userdata( $author_id );
            $author_send_email = self::luser_email($userObj);
            $subj_args = array(
                'ID'        => $post_id,
                'date'      => Esb_Class_Date::i18n($postObj->post_date),
            );
            $temp_args     = array(
                'ID'                => $post_id,
                'payment_method'    => townhub_addons_payment_names( get_post_meta( $post_id, ESB_META_PREFIX.'payment_method', true ) ),
                'email'             => get_post_meta( $post_id, ESB_META_PREFIX.'withdrawal_email', true ),
                'amount'            => townhub_addons_get_price_formated( get_post_meta( $post_id, ESB_META_PREFIX.'amount', true ) ),
                'notes'             => get_post_meta( $post_id, ESB_META_PREFIX.'notes', true ),

                'date'              => Esb_Class_Date::i18n($postObj->post_date),
                'author_email'      => $author_send_email,
                'author_name'       => $userObj->display_name,
            );
            // send admin notification email
            $email_recipients = townhub_addons_get_option('emails_admin_new_withdrawal_recipients') ? townhub_addons_get_option('emails_admin_new_withdrawal_recipients') : get_bloginfo('admin_email');
            if (townhub_addons_get_option('emails_admin_new_withdrawal_enable') == 'yes') {

                $email_subject = self::process_email_template(townhub_addons_get_option('emails_admin_new_withdrawal_subject'), $subj_args);
                $email_template = self::process_email_template(townhub_addons_get_option('emails_admin_new_withdrawal_temp'), $temp_args);

                $headers = array('Reply-To: ' . $userObj->display_name . ' ' . '<' . $author_send_email . '>');
                self::do_wp_mail($email_recipients, $email_subject, $email_template, $headers);
            }


            // send author email
            if (townhub_addons_get_option('emails_auth_new_withdrawal_enable') == 'yes') {

                $email_subject = self::process_email_template(townhub_addons_get_option('emails_auth_new_withdrawal_subject'), $subj_args);
                $email_template = self::process_email_template(townhub_addons_get_option('emails_auth_new_withdrawal_temp'), $temp_args);

                $auth_replies = array();
                foreach ((array) $email_recipients as $em) {
                    $auth_replies[] = '<' . $em . '>';
                }

                $headers = array('Reply-To: ' . implode(',', $auth_replies));

                self::do_wp_mail($author_send_email, $email_subject, $email_template, $headers);
            }
            // end new listing author email
        }
        // send email for submit new listing only
    }
    public static function edit_withdrawal_approved($post_id = 0)
    {
        if ( !empty($post_id) ) {
            
            $postObj = get_post($post_id);
            $userObj = get_userdata( get_post_meta( $post_id, ESB_META_PREFIX.'user_id', true ) );
            $author_send_email = self::luser_email($userObj);
            $subj_args = array(
                'ID'        => $post_id,
                'date'      => Esb_Class_Date::i18n($postObj->post_date),
            );
            $temp_args     = array(
                'ID'                => $post_id,
                'payment_method'    => townhub_addons_payment_names( get_post_meta( $post_id, ESB_META_PREFIX.'payment_method', true ) ),
                'email'             => get_post_meta( $post_id, ESB_META_PREFIX.'withdrawal_email', true ),
                'amount'            => townhub_addons_get_price_formated( get_post_meta( $post_id, ESB_META_PREFIX.'amount', true ) ),
                'notes'             => get_post_meta( $post_id, ESB_META_PREFIX.'notes', true ),

                'date'              => Esb_Class_Date::i18n($postObj->post_date),
                'author_email'      => $author_send_email,
                'author_name'       => $userObj->display_name,
            );
            // send author email
            if (townhub_addons_get_option('emails_auth_completed_withdrawal_enable') == 'yes') {

                $email_subject = self::process_email_template(townhub_addons_get_option('emails_auth_completed_withdrawal_subject'), $subj_args);
                $email_template = self::process_email_template(townhub_addons_get_option('emails_auth_completed_withdrawal_temp'), $temp_args);

                // $auth_replies = array();
                // foreach ((array) $email_recipients as $em) {
                //     $auth_replies[] = '<' . $em . '>';
                // }

                // $headers = array('Reply-To: ' . implode(',', $auth_replies));

                self::do_wp_mail($author_send_email, $email_subject, $email_template);
            }
            // end new listing author email
        }
        // send email for submit new listing only
    }
    public static function sub_will_expire($author_id = 0, $sub_id = 0){
        if ( !empty($author_id) ) {
            $userObj = get_userdata( $author_id );
            $author_send_email = self::luser_email($userObj);

            $enddate = get_post_meta( $sub_id, ESB_META_PREFIX.'end_date', true );
            $subj_args = array(
                'ID'        => $sub_id,
                'date'      => Esb_Class_Date::i18n('now', true),
            );
            $temp_args     = array(
                'ID'                => $sub_id,
                'date'              => Esb_Class_Date::i18n('now', true),
                'author_email'      => $author_send_email,
                'author_name'       => $userObj->display_name,
                'expire_date'       => Esb_Class_Date::i18n($enddate),
            );

            if (townhub_addons_get_option('emails_sub_will_expire_enable') == 'yes') {

                $email_subject = self::process_email_template(townhub_addons_get_option('emails_sub_will_expire_subject'), $temp_args);
                $email_template = self::process_email_template(townhub_addons_get_option('emails_sub_will_expire_temp'), $temp_args);

                self::do_wp_mail($author_send_email, $email_subject, $email_template);
            }
        }
    }

}
Esb_Class_Emails::init();
// $class_email = new Esb_Class_Emails();
// $class_email->init();
