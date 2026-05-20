<?php
/**
 * The template for displaying `Page Tour`
 * Template Name: Page Tour
 * Template Post Type: page
 *
 * @author Vie
 */
use HD\Helper;
\defined( 'ABSPATH' ) || die;

// header
get_header( 'home' );

if ( have_posts() ) {
	the_post();
}
if ( post_password_required() ) {
	echo get_the_password_form();
	get_footer( 'home' );
	return;
}

$ACF = \HD\Helper::getFields( get_the_ID() );
$tour_banner_flexible = ! empty( $ACF['tour_banner_flexible'] ) ? (array) $ACF['tour_banner_flexible'] : false;
$tour_flexible = ! empty( $ACF['tour_flexible'] ) ? (array) $ACF['tour_flexible'] : false;
?>

<div class="tour-head-banner">
    <?php 
        if ( $tour_banner_flexible ) {
            foreach ( $tour_banner_flexible as $section ) {
                $acf_fc_layout = $section['acf_fc_layout'] ?? '';
                if ( $acf_fc_layout ) {
                    \HD\Helper::blockTemplate( 'template-parts/tour/' . $acf_fc_layout, $section );
                }
            }
        }
    ?>
</div>
<?php $bg_tour = get_field('bg_lists_tour','option');
$hot_tour = get_field('select_hot_tour_sidebar','option');
 ?>
<div class="tour-main-content section-padding">
    <?php if($bg_tour){ ?>
        <style>
            .tour-main-content {
                background-image: url(<?php echo $bg_tour; ?>);
                background-repeat: no-repeat;
                background-size: cover;
            }
        </style>
    <?php } ?>
    <div class="container">
        <div class="wrapper flex flex-x">
            <div class="cell cell-content">
                <?php 
                    if ( $tour_flexible ) {
                        foreach ( $tour_flexible as $section ) {
                            $acf_fc_layout = $section['acf_fc_layout'] ?? '';
                            if ( $acf_fc_layout ) {
                                \HD\Helper::blockTemplate( 'template-parts/tour/' . $acf_fc_layout, $section );
                            }
                        }
                    }
                ?>
            </div>
            <div class="cell cell-sidebar">
                <?php 
                if($hot_tour){
                    $query_args = array(
                        'post_type' => 'tour',
                        'posts_per_page' => 6,
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'tour-category',
                                'field' => 'term_id',
                                'terms' => $hot_tour,
                            ),
                        ),
                    );
                    $posts_query = new WP_Query( $query_args );
                    if ( $posts_query->have_posts() ) {  ?>
                        <ul class="list-tour tour-hot">
                            <?php while ( $posts_query->have_posts() ) {
                                $posts_query->the_post(); ?>
                                <li class="tour_item">
                                    <div class="tour_item-wrapper">
                                        <div class="tour_item-inner">
                                            <div class="tour_item-head relative">
                                                <a href="<?php echo get_permalink(); ?>" class="block relative tour_item-head-link">
                                                    <?php the_post_thumbnail('large', ['class' => 'tour-feature-image']) ?> 
                                                    <h3 class="tour_item-title"><?php the_title( ); ?></h3>
                                                </a>
                                                <?php 
                                                    $tour_price = get_field('tour_price');
                                                    $tour_price_discount = get_field('tour_price_discount');
                                                    if ( $tour_price || $tour_price_discount) {
                                                        if ( !empty($tour_price) ) {
                                                            if ( !empty($tour_price_discount) ) { ?>
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
                                                                            $percentage = calculatePercentage($tour_price ,  $tour_price_discount );
                                                                            $result = round($percentage, 0, PHP_ROUND_HALF_UP);
                                                                            echo $result . '%';
                                                                        ?>
                                                                    </span>
                                                                <?php 
                                                            } else  { ?>
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
                                                        } 
                                                    } else { ?>
                                                        <span class="tour-price">
                                                            <ins>
                                                                <span class="tour-price-amount">
                                                                    <bdi>Giá liên hệ!</bdi>
                                                                </span>
                                                            </ins>
                                                        </span>
                                                    <?php } ?>
                                                <span class="tour-hot-bage"><?php echo __('Hot tour','vie-tour-hot-elementor-addon'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php } ?>
                        </ul>
                        <?php
                        wp_reset_postdata();
                    }
                } ?>
            </div>
        </div>
    </div>
    
</div>

<div class="hotel-general">
	<?php get_template_part('template-parts/tour/section_why_choose'); ?>
	<?php echo do_shortcode('[section_partner]'); ?>
	<?php get_template_part('template-blocks/section-newspapers'); ?>
	<?php get_template_part('template-blocks/section-posts'); ?>
</div>

<?php

// footer
get_footer( 'home' );