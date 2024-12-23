<?php
/* add_ons_php */

$current_user = wp_get_current_user();  


$user_role = townhub_addons_get_user_role();

if( in_array($user_role, array('listing_author','seller','wcfm_vendor','shop_manager')) && ( $plan_id = Esb_Class_Membership::current_plan( get_current_user_id() ) ) != '' ){
    $plan_string = sprintf(__('<span>Membership Plan: </span> <strong>%1$s</strong>', 'townhub-add-ons'), get_the_title($plan_id));
    $plan_desc = sprintf(__('<p>You are on <a>%1$s</a>. Use link bellow to view details or upgrade.</p><a class="tfp-det-btn color2-bg" href="%2$s">Details</a>', 'townhub-add-ons'), get_the_title($plan_id), get_permalink( esb_addons_get_wpml_option('packages_page') ) );
}else{
    $plan_string = sprintf(__('<span>Your are: </span> <strong>%1$s</strong>', 'townhub-add-ons'), townhub_addons_get_user_role_name());
    $plan_desc = sprintf(__('<p>You are <a>%1$s</a>. Order an membership plan to submit listings.</p><a class="tfp-det-btn color2-bg" href="%2$s">Membership Plans</a>', 'townhub-add-ons'), townhub_addons_get_user_role_name(), get_permalink( esb_addons_get_wpml_option('packages_page') ) );
}

$loggedin_is_author = Esb_Class_Membership::is_author();
if( $loggedin_is_author ){
    $views_count = 0;
    $bookmarks_count = 0;
    $comments_count = 0;
    $post_args = array(
        'post_type'         => 'listing',
        'author'            => $current_user->ID,
        'posts_per_page'    => -1,
        'post_status'       => array('publish','pending','future','private'), // 'publish',
        'fields'            => 'ids'
    );
    $posts_get = get_posts( $post_args );
    foreach ( $posts_get as $pid ) {
        $views_count += Esb_Class_LStats::get_stats( $pid );
        $bookmarks_count += Esb_Class_Listing_CPT::get_bookmark_count( $pid );
        $comments_count += get_comments_number( $pid );
    }
}
    
?>
<!--  section  -->
<section class="parallax-section dashboard-header-sec gradient-bg" data-scrollax-parent="true">
    
    <?php 
    // <section class="parallax-section single-par" data-scrollax-parent="true">
    $hdbg = townhub_addons_get_option('dbheader_image'); 
    if( !empty($hdbg) && !empty($hdbg['id']) ): ?>
    <div class="bg par-elem" data-bg="<?php echo wp_get_attachment_image_url( $hdbg['id'], 'full' );?>" data-scrollax="properties: { translateY: '30%' }"></div>
    <div class="overlay op7"></div>
    <?php endif; ?>
    <div class="container">

        <?php Esb_Class_Dashboard::breadcrumbs();?>
        <!--Tariff Plan menu-->
        <div class="tfp-btn"><?php echo $plan_string; ?></div>
        <div class="tfp-det"><?php echo $plan_desc; ?></div>
        <!--Tariff Plan menu end-->
        <div class="dashboard-header_conatiner fl-wrap dashboard-header_title">
            <?php echo sprintf(__( '<h1>Welcome  : <span>%s</span></h1>', 'townhub-add-ons' ), $current_user->display_name); ?>
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="dashboard-header fl-wrap dashboard-header-sec">
        <div class="container">
            <div class="dashboard-header_conatiner fl-wrap flex-items-center flw-wrap">
                <div class="dashboard-header-avatar">
                    <?php 
                        echo get_avatar($current_user->user_email,'100','https://0.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?s=100', $current_user->display_name );
                    ?>
                    <?php if( 'profile' == get_query_var('dashboard') ): ?>
                    <a href="#profile-images" class="color-bg edit-prof_btn custom-scroll-link"><i class="fal fa-edit"></i></a>
                    <?php else: ?>
                    <a href="<?php echo Esb_Class_Dashboard::screen_url('profile'); ?>" class="color-bg edit-prof_btn"><i class="fal fa-edit"></i></a>
                    <?php endif; ?>
                </div>
                <?php if( $loggedin_is_author ): ?>
                <div class="dashboard-header-stats-wrap">
                    <div class="dashboard-header-stats">
                        <div class="swiper-container">
                            <div class="swiper-wrapper">
                                <!--  dashboard-header-stats-item -->
                                <div class="swiper-slide">
                                    <div class="dashboard-header-stats-item">
                                        
                                        <i class="fal fa-map-marked"></i>
                                        <?php echo sprintf(__( 'Active Listings <span>%d</span>', 'townhub-add-ons' ), count( $posts_get ) ); ?>
                                       
                                    </div>
                                </div>

                                
                                <!--  dashboard-header-stats-item end -->
                                <!--  dashboard-header-stats-item -->
                                <div class="swiper-slide">
                                    <div class="dashboard-header-stats-item">
                                        <i class="fal fa-chart-bar"></i>
                                        <?php echo sprintf(__( 'Listing Views <span>%s</span>', 'townhub-add-ons' ), $views_count ); ?>
                                    </div>
                                </div>
                                <!--  dashboard-header-stats-item end -->
                                <!--  dashboard-header-stats-item -->
                                <div class="swiper-slide">
                                    <div class="dashboard-header-stats-item">
                                        <i class="fal fa-comments-alt"></i>
                                        <?php echo sprintf(__( 'Total Reviews <span>%s</span>', 'townhub-add-ons' ), $comments_count ); ?>
                                    </div>
                                </div>
                                <!--  dashboard-header-stats-item end -->
                                <!--  dashboard-header-stats-item -->
                                <div class="swiper-slide">
                                    <div class="dashboard-header-stats-item">
                                        <i class="fal fa-heart"></i>
                                        <?php echo sprintf(__( 'Times Bookmarked <span>%s</span>', 'townhub-add-ons' ), $bookmarks_count ); ?>
                                    </div>
                                </div>
                                <!--  dashboard-header-stats-item end -->
                            </div>
                        </div>
                    </div>
                    <!--  dashboard-header-stats  end -->
                    <div class="dhs-controls flex-items-center">
                        <div class="dhs dhs-prev"><i class="fal fa-angle-left"></i></div>
                        <div class="dhs dhs-next"><i class="fal fa-angle-right"></i></div>
                    </div>
                </div>
                <!--  dashboard-header-stats-wrap end -->
                <a class="add_new-dashboard" href="<?php echo townhub_addons_add_listing_url(); ?>"><?php _e( 'Add Listing <i class="fal fa-layer-plus"></i>', 'townhub-add-ons' ); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if( townhub_addons_get_option('dbheader_hide_circle') != 'yes' ): ?>
    <div class="gradient-bg-figure" style="right:-30px;top:10px;"></div>
    <div class="gradient-bg-figure" style="left:-20px;bottom:30px;"></div>
    <div class="circle-wrap" style="left:120px;bottom:120px;" data-scrollax="properties: { translateY: '-200px' }">
        <div class="circle_bg-bal circle_bg-bal_small"></div>
    </div>
    <div class="circle-wrap" style="right:420px;bottom:-70px;" data-scrollax="properties: { translateY: '150px' }">
        <div class="circle_bg-bal circle_bg-bal_big"></div>
    </div>
    <div class="circle-wrap" style="left:420px;top:-70px;" data-scrollax="properties: { translateY: '100px' }">
        <div class="circle_bg-bal circle_bg-bal_big"></div>
    </div>
    <div class="circle-wrap" style="left:40%;bottom:-70px;"  >
        <div class="circle_bg-bal circle_bg-bal_middle"></div>
    </div>
    <div class="circle-wrap" style="right:40%;top:-10px;"  >
        <div class="circle_bg-bal circle_bg-bal_versmall" data-scrollax="properties: { translateY: '-350px' }"></div>
    </div>
    <div class="circle-wrap" style="right:55%;top:90px;"  >
        <div class="circle_bg-bal circle_bg-bal_versmall" data-scrollax="properties: { translateY: '-350px' }"></div>
    </div>
    <?php endif; ?>
</section>
<!--  section  end-->


