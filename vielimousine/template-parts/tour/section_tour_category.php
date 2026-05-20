<?php
\defined( 'ABSPATH' ) || die;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_tour_category' ) {
	return;
}
$title = $args['title'] ?? '';
$desc  = $args['desc'] ?? '';
$sl_tour_cat  = $args['sl_tour_cat'] ?? '';
$hotline_thue_xe = get_field('hotline_thue_xe','option');
$zalo = get_field('zalo','option'); ?>
<section class="section tour-category relative">
    <div class="group-heading">
        <?php if($title){
            echo '<h2 class="heading-title">'. $title .'</h2>';
        }
        if($desc){
            echo '<div class="desc">'. $desc .'</div>';
        } ?>
    </div>
    <?php 
    if($sl_tour_cat){ 
        $query_args = array(
            'post_type' => 'tour',
            'posts_per_page' => 6,
            'tax_query' => array(
                array(
                    'taxonomy' => 'tour-category',
                    'field' => 'term_id',
                    'terms' => $sl_tour_cat,
                ),
            ),
        );
        $posts_query = new WP_Query( $query_args );
        if ( $posts_query->have_posts() ) { 
            ?>
                <ul class="list-tour">
                    <?php
                    while ( $posts_query->have_posts() ) {
                        $posts_query->the_post();
                        ?>
                        <li class="tour_item">
                            <div class="tour_item-wrapper">
                                <div class="tour_item-inner">
                                    <div class="tour_item-head relative">
                                        <a href="<?php echo get_permalink(); ?>" class="block relative tour_item-head-link">
                                            <?php the_post_thumbnail('large', ['class' => 'tour-feature-image']) ?> 
                                        </a>
                                        <?php 
                                            $tour_price = get_field('tour_price');
                                            $tour_price_discount = get_field('tour_price_discount');
                                            
                                            if ( $tour_price || $tour_price_discount) {
                                                if ( !empty($tour_price) ) {
                                                    
                                                    if ( !empty($tour_price_discount) ) {
                                                        ?>
                                                            <span class="tour-price">
                                                                 <ins>
                                                                    <span class="tour-price-amount">
                                                                        <bdi><?php echo formatCurrency($tour_price_discount); ?><span class="tour-rice-currencySymbol">vnđ</span></bdi>
                                                                    </span>
                                                                </ins>
                                                                <del>
                                                                    
                                                                    <span class="tour-price-amount">
                                                                        <bdi><?php echo formatCurrency($tour_price); ?><span class="tour-rice-currencySymbol">vnđ</span></bdi>
                                                                    </span>
                                                                </del>
                                                            </span>
                                                            <span class="tour-dis-per">
                                                                <?php
                                                                    $percentage = calculatePercentage($tour_price, $tour_price_discount);
                                                                    $result = round($percentage, 0, PHP_ROUND_HALF_UP);
                                                                    echo $result . '%';
                                                                ?>
                                                            </span>
                                                        <?php 
                                                    } else  {
                                                        ?>
                                                            <span class="tour-price">
                                                                <ins>
                                                                    <span class="tour-price-amount">
                                                                        <bdi>
                                                                            <?php 
                                                                                echo formatCurrency($tour_price);
                                                                            ?>
                                                                            <span class="tour-rice-currencySymbol">vnđ</span>
                                                                        </bdi>
                                                                    </span>
                                                                </ins>
                                                            </span>
                                                        <?php
                                                    }
                                                } else {
                                                    ?>
                                                        
                                                    <?php
                                                } 
                                            }else {
                                                ?>
                                                    <span class="tour-price">
                                                        <ins>
                                                            <span class="tour-price-amount">
                                                                <bdi>Giá liên hệ!</bdi>
                                                            </span>
                                                        </ins>
                                                    </span>
                                                <?php
                                            }
                                        ?>
                                    </div>
                                    <div class="tour_item-body">
                                        <div class="tour_item-body-wrap">
                                            <h3 class="tour_item-title">
                                                <a href="<?php echo get_permalink(); ?>" class="tour-link">
                                                    <?php the_title( ); ?>
                                                </a> 
                                            </h3>
                                        </div>
                                        <div class="tour_item-info">
                                            <?php 
                                                if (  have_rows('tour_transport_repeat') ||  get_field('tour_time_booking')  ){
                                                    ?>
                                                        <div class="tour_item-info-meta">
                                                            <span class="tour_time_booking"><i class="fa-solid fa-clock"></i> <?php echo get_field('tour_time_booking'); ?></span>
                                                            <?php 
                                                                if( have_rows('tour_transport_repeat') ){
                                                                    echo '<div class="tour-transport">';
                                                                    while( have_rows('tour_transport_repeat') ) {
                                                                        the_row();
                                                                        $tour_transport_icon = get_sub_field('tour_transport_icon');
                                                                        ?>
                                                                        <img src="<?php echo $tour_transport_icon['url'] ?>" alt="" class="tour_transport-icon">
                                                                        <?php
                                                                    }
                                                                    echo '</div>';
                                                                }
                                                            ?>
                                                        </div>
                                                    <?php
                                                }
                                            ?>
                                            <?php
                                                if( have_rows('tour_outstanding_repeat') ){
                                                    ?>
                                                    <div class="tour_item-info-short-des">
                                                        <ul>
                                                            <?php
                                                            while( have_rows('tour_outstanding_repeat') ) {
                                                                the_row();
                                                                $tour_outstanding_item = get_sub_field('tour_outstanding_item');
                                                                ?>
                                                                <li class="tour_outstanding_item"><?php echo  $tour_outstanding_item; ?></li>
                                                                <?php
                                                            } 
                                                        ?>
                                                        </ul>
                                                    </div>
                                                <?php
                                                }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="tour_item-foot">
                                        <a href="<?php echo get_permalink(); ?>" class="tour-booknow-btn">ĐẶT NGAY</a>
                                        <div class="tour_contact-btn">
                                            <a href="tel:<?php echo $hotline_thue_xe; ?>" class="hotline">
                                                <i class="fa-solid fa-phone-volume"></i> Hotline
                                            </a>
                                            <a href="<?php echo $zalo; ?>" target="_blank" class="zalo">
                                                <img src="<?php echo get_template_directory_uri() . '/resources/img/ic-zalo.png'; ?>" alt="">
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <?php
                    }
                    ?>
                </ul>
            <?php
            wp_reset_postdata();
        }
    } ?>
</section>