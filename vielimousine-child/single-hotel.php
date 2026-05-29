<?php
/**
 * Single Hotel template (child theme).
 *
 * Mirror parent vielimousine/single-hotel.php structure (9/3 layout, hooks,
 * gallery/combo/utilities rendered từ ACF), nhưng:
 *   - Inject Vue booking section vào main column (sau combo, trước utilities).
 *   - Replace sidebar Contact Form 7 bằng Vue BookingWidget (sticky tóm tắt).
 *
 * Vue app (public-app/) handle: search, room cards (dual price), inline checkout,
 * sticky widget. PHP injects room data + hotel meta via JSON script tag.
 */

defined('ABSPATH') || die;

get_header('single');

if (have_posts()) {
    the_post();
}

if (post_password_required()) {
    echo get_the_password_form();
    get_footer('single');
    return;
}

do_action('hd_single_before_action');

$postId = (int) get_the_ID();

// Load hotel + rooms qua Repository layer (kiến trúc §1.6 — không raw $wpdb).
$hotelRepo = \Vie\Container::get(\Vie\Repository\HotelRepository::class);
$roomRepo  = \Vie\Container::get(\Vie\Repository\RoomRepository::class);
$hotel     = $hotelRepo->findByPostId($postId);
$hotelId   = $hotel ? (int) $hotel['id'] : 0;
$roomsData = [];
if ($hotelId > 0) {
    $roomsResult = $roomRepo->all([
        'hotel_id'  => $hotelId,
        'is_active' => 1,
        'sort'      => 'sort_order',
        'order'     => 'asc',
        'per_page'  => 100,
    ]);
    $rooms = $roomsResult['data'] ?? [];
    $roomsData = array_map(static function (array $r): array {
        $amenities = $r['amenities'] ?? [];
        if (is_string($amenities)) {
            $decoded   = json_decode($amenities, true);
            $amenities = is_array($decoded) ? $decoded : [];
        }
        $amenities = is_array($amenities) ? array_values(array_filter($amenities, 'is_string')) : [];
        $thumb = '';
        if (!empty($r['thumbnail_id'])) {
            $thumb = (string) wp_get_attachment_image_url((int) $r['thumbnail_id'], 'medium_large');
        }
        return [
            'id'                => (int) $r['id'],
            'name'              => (string) $r['name'],
            'description'       => $r['description'] ?: null,
            'thumbnail_url'     => $thumb ?: null,
            'included_adults'   => (int) $r['included_adults'],
            'max_adults'        => (int) $r['max_adults'],
            'max_children'      => (int) $r['max_children'],
            'base_price'        => (int) $r['base_price'],
            'extra_adult_price' => (int) $r['extra_adult_price'],
            'area'              => $r['area'] ? (int) $r['area'] : null,
            'bed_type'          => $r['bed_type'] ?: null,
            'bed_count'         => $r['bed_count'] ? (int) $r['bed_count'] : null,
            'view'              => $r['view'] ?: null,
            'floor'             => $r['floor'] ?: null,
            'amenities'         => $amenities,
        ];
    }, $rooms ?? []);
}
?>
<section class="section section-page section-single singular">
    <div class="container">
        <div class="vie-row flex flex-x">
            <div class="cell vie-col-9">
                <main id="main" class="site-main">
                    <article <?php post_class('article'); ?>>

                        <div class="entry-head-content">
                            <div class="vie-row flex flex-x">
                                <div class="entry-head-info">
                                    <p class="heading-title hotel_single-title"><?php the_title(); ?></p>
                                    <?php
                                    $hotel_address = function_exists('get_field') ? get_field('hotel_address', $postId) : '';
                                    if ($hotel_address) : ?>
                                        <div class="hotel_single-address">
                                            <span class="address"><?php echo esc_html($hotel_address); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="single_hotel-meta flex flex-x">
                                        <div class="hotel_item-type flex flex-x">
                                            <?php
                                            if (function_exists('get_hotel_type_links')) {
                                                $hotel_links = get_hotel_type_links('hotel-convenient');
                                                if (!empty($hotel_links)) echo $hotel_links;
                                            }
                                            ?>
                                        </div>
                                        <?php if (function_exists('getPostRatingInfo') && function_exists('displayRatingStars')) :
                                            $ratingInfo = getPostRatingInfo($postId);
                                            ?>
                                            <div class="rating">
                                                <div class="post_item-rating flex">
                                                    <?php echo displayRatingStars($ratingInfo['average_rating']); ?>
                                                    Đánh giá: <?php echo esc_html($ratingInfo['average_rating']); ?> (<?php echo (int) $ratingInfo['rating_count']; ?>)
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="hotel_item-foot">
                                    <div class="hotel_item-foot-left">
                                        <span class="hotel_note"><?php echo __('Thanh toán nhận ưu đãi', 'vie'); ?></span>
                                        <?php
                                        if (function_exists('display_price')) {
                                            $hotel_price = function_exists('get_field') ? get_field('hotel_price') : '';
                                            $hotel_price_discount = function_exists('get_field') ? get_field('hotel_price_discount') : '';
                                            echo display_price($hotel_price, $hotel_price_discount);
                                        }
                                        ?>
                                    </div>
                                    <div class="hotel_item-foot-right">
                                        <a href="#form_booking" class="hotel-booknow-btn"><?php echo __('ĐẶT NGAY', defined('TEXT_DOMAIN') ? TEXT_DOMAIN : 'vie'); ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Gallery from ACF (parent theme)
                        if (function_exists('get_field') && get_field('hotel_gallery')) :
                            $hotel_gallery = get_field('hotel_gallery');
                            if (is_array($hotel_gallery) && count($hotel_gallery) > 0) : ?>
                                <div class="hotel_gallery">
                                    <div class="hotel_gallery-wrapper">
                                        <div id="hotel_gallery-list">
                                            <?php foreach ($hotel_gallery as $tour_image) : ?>
                                                <div class="img">
                                                    <a href="<?php echo esc_url($tour_image['url']); ?>" class="block relative" data-fancybox="gallery-<?php echo $postId; ?>">
                                                        <?php echo wp_get_attachment_image($tour_image['id'], 'large'); ?>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="hotel_gallery-nav">
                                            <?php foreach ($hotel_gallery as $tour_image) : ?>
                                                <div class="img-dots">
                                                    <div class="img block relative">
                                                        <?php echo wp_get_attachment_image($tour_image['id'], 'large'); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif;
                        endif; ?>

                        <?php
                        // Hotel combo block from ACF
                        if (function_exists('get_field') && get_field('hotel_combo')) : ?>
                            <div class="hotel_combo">
                                <div class="hotel_combo-inner">
                                    <?php if (get_field('hotel_combo_title')) : ?>
                                        <h2 class="single-tour-title-content"><?php echo esc_html(get_field('hotel_combo_title')); ?></h2>
                                    <?php endif; ?>
                                    <div class="hotel_combo-content">
                                        <?php echo get_field('hotel_combo'); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php /* === VUE BOOKING SECTION: rooms + inline checkout === */ ?>
                        <?php if ($hotelId > 0) : ?>
                            <div class="vie-booking-section" id="form_booking">
                                <h2 class="single-tour-title-content">Đặt phòng</h2>
                                <div
                                    id="vie-booking-main"
                                    class="vie-booking-main"
                                    data-vie-public-hotel
                                    data-hotel-id="<?php echo (int) $hotelId; ?>"
                                >
                                    <script type="application/json"><?php
                                        echo wp_json_encode($roomsData, JSON_UNESCAPED_UNICODE);
                                    ?></script>
                                    <noscript>
                                        <p>Vui lòng bật JavaScript để đặt phòng trực tuyến. Liên hệ hotline để đặt qua điện thoại.</p>
                                    </noscript>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="vie-booking-unavailable">
                                <p>Khách sạn này chưa được cấu hình hệ thống đặt phòng trực tuyến. Vui lòng liên hệ để được hỗ trợ.</p>
                            </div>
                        <?php endif; ?>

                        <?php
                        // Hotel utilities from ACF
                        if (function_exists('have_rows') && have_rows('hotel_utilities')) : ?>
                            <div class="hotel_utilities">
                                <div class="hotel_utilities-inner">
                                    <?php if (get_field('hotel_utilities_title')) : ?>
                                        <h2 class="single-tour-title-content"><?php echo esc_html(get_field('hotel_utilities_title')); ?></h2>
                                    <?php endif; ?>
                                    <ul class="hotel_utilities-list">
                                        <?php while (have_rows('hotel_utilities')) {
                                            the_row();
                                            $hotel_utility_item_text = get_sub_field('hotel_utility_item_text');
                                            $hotel_utility_item_link = get_sub_field('hotel_utility_item_link'); ?>
                                            <li class="hotel_utilities-item">
                                                <a href="<?php echo esc_url($hotel_utility_item_link); ?>" class="hotel_utilities-item-link">
                                                    <?php echo esc_html($hotel_utility_item_text); ?>
                                                </a>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="entry-content">
                            <?php if (!empty(get_the_content())) : ?>
                                <h2 class="single-tour-title-content">Thông tin <?php the_title(); ?></h2>
                                <?php echo do_shortcode('[ez-toc]'); ?>
                                <?php the_content(); ?>
                            <?php endif; ?>
                        </div>

                    </article>
                </main>
            </div>

            <aside class="cell vie-col-3">
                <div class="single_tour_sidebar-wrapper">
                    <div class="single-tour-sidebar">
                        <div class="single_tour_sidebar-price">
                            <div class="single_tour_sidebar-price-inner">
                                <h4 class="singl_hotel-form-title">ĐẶT KHÁCH SẠN</h4>
                            </div>
                        </div>
                        <div class="single_tour_sidebar-form">
                            <div class="single_tour_sidebar-form-inner">
                                <?php if ($hotelId > 0) : ?>
                                    <div id="vie-booking-widget" data-vie-public-widget></div>
                                <?php else : ?>
                                    <p>Vui lòng liên hệ hotline để đặt phòng tại khách sạn này.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (function_exists('tour_content_sidebar')) tour_content_sidebar(); ?>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php

do_action('hd_single_after_action');

get_footer('single');
