<?php
use HD\Helper;
defined( 'ABSPATH' ) || die;

$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_deal_hot' ) {
    return;
}

$heading_title = $args['title'] ?? '';
$sl_taxonomy = $args['sl_taxonomy'] ?? false;
$hotel_query = Helper::queryByTerms( $sl_taxonomy, 'hotel', 'hotel-category', false, 6 );
$btn_link = $args['btn_link'] ?? '';
$btn_name = $args['btn_name'] ?? 'Xem thêm';
?>
<section class="section home-deal section-padding">
    <div class="container">
        <?php if ($heading_title) {
            echo '<h2 class="heading-title text-center">' . $heading_title . '</h2>';
        } ?>
        <?php if ($hotel_query) : ?>
            <ul class="wrapper gap-20">
                <?php while ($hotel_query->have_posts()) : $hotel_query->the_post(); 
                    $post_title = get_the_title();
                    $post_permalink = get_the_permalink();
                    $post_thumbnail = get_the_post_thumbnail(null, 'full', ['alt' => esc_attr($post_title)]);
                    $rank_terms = get_the_terms(get_the_ID(), 'hotel-rank');
                    $hotel_address = get_field('hotel_address');
                    $combo_text = get_field('combo_text');
                    $hotel_price = get_field('hotel_price');
                    $hotel_price_discount = get_field('hotel_price_discount');
                    ?>
                    <li class="item relative flex flex-column">
                        <a href="<?= esc_url($post_permalink); ?>" class="cover relative">
                            <?= $post_thumbnail ?>
                            <span class="bage">Hot Deal</span>
                        </a>
                        <div class="content flex flex-column">
                            <h3 class="title">
                                <a href="<?= esc_url($post_permalink); ?>"><?= esc_html($post_title); ?></a>
                            </h3>
                            <?php if($combo_text){
                                echo '<div class="combo-text" style="color: '. $combo_text['color'] .'">'. $combo_text['text'] .'</div>';
                            } ?>
                            <!-- <div class="rating flex flex-x align-center justify-space-between">
                                <?php
                                    // if ($rank_terms && !is_wp_error($rank_terms)) {
                                    //     echo '<span class="text">Tiêu chuẩn ' . esc_html($rank_terms[0]->name) . '</span>';
                                    // }
                                ?>
                                <?php
                                    // if ($rank_terms && !is_wp_error($rank_terms)) {
                                    //     $rank_name = $rank_terms[0]->name; 
                                    //     preg_match('/\d+/', $rank_name, $matches);
                                    //     $star_count = isset($matches[0]) ? intval($matches[0]) : 0;
                                    //     echo '<div class="star">';
                                    //     for ($i = 0; $i < $star_count; $i++) {
                                    //         echo '<i class="fa-solid fa-star"></i>';
                                    //     }
                                    //     echo '</div>';
                                    // }
                                ?>
                            </div> -->
                            <?php if($hotel_address) { echo '<div class="address">'. $hotel_address .'</div>'; } ?>
                                <?php if($hotel_price || $hotel_price_discount){
                                    echo '<div class="group-price flex align-center justify-space-between">';
                                    if($hotel_price){
                                        echo '<div class="price-sale">'. number_format($hotel_price, 0, ',', '.') .'</div>';
                                    }
                                    if($hotel_price_discount){
                                        echo '<div class="price-main">'. number_format($hotel_price_discount, 0, ',', '.') .'</div>';
                                    }
                                    echo '</div>';
                                }
                            ?>
                        </div>
                    </li>
                <?php endwhile; wp_reset_postdata(); ?>
            </ul>
        <?php endif; ?>
        <?php
            if($btn_link){
                echo '<a href="'. $btn_link .'" class="btn-main ml-auto mr-auto">'. $btn_name .'</a>';
            }
        ?>
    </div>
</section>
