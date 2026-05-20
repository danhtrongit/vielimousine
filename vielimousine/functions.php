<?php
/**
 * Theme functions and definitions
 *
 * @author Vie
 */

$current_theme = wp_get_theme();
$parent_theme  = $current_theme->parent() ?: $current_theme;

$theme_version = $parent_theme->get( 'Version' ) ?: false;
$theme_author  = $parent_theme->get( 'Author' ) ?: 'Vie';
$theme_uri     = $parent_theme->get( 'ThemeURI' ) ?: 'https://vielimousine.com';
$text_domain   = $parent_theme->get( 'TextDomain' ) ?: 'vie';

define( 'TEXT_DOMAIN', $text_domain );
define( 'THEME_VERSION', $theme_version );
define( 'THEME_URI', $theme_uri );
define( 'AUTHOR', $theme_author );

define( 'THEME_PATH', untrailingslashit( get_template_directory() ) . DIRECTORY_SEPARATOR ); // **/wp-content/themes/**/
define( 'THEME_URL', untrailingslashit( esc_url( get_template_directory_uri() ) ) . '/' );  // http(s)://**/wp-content/themes/**/

const INC_PATH   = THEME_PATH . 'inc' . DIRECTORY_SEPARATOR;
const ASSETS_URL = THEME_URL . 'assets/';

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	error_log( 'Autoloader not found: ' . __DIR__ . '/vendor/autoload.php' );
	wp_die( __( 'Error locating autoloader. Please run <code>composer install</code>.', TEXT_DOMAIN ) );
}

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/inc/settings.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/css.php';
require_once __DIR__ . '/inc/js.php';
require_once __DIR__ . '/inc/hotel-functions.php';
require_once __DIR__ . '/inc/cta-functions.php';

// Initialize theme.
( \HD\Theme::get_instance() );

// VIE CUSTOM

//Format Currency
function format_Currency($amount) {
	$formattedAmount = number_format($amount, 0, ',', '.');
	return $formattedAmount;
}

function tour_calculatePercentage($originalPrice, $discountedPrice) {
	if ($originalPrice == 0) {
		return 0; // Tránh chia cho 0
	}

	$percentage = (($originalPrice - $discountedPrice) / $originalPrice) * 100;
	return $percentage;
}

function display_price($price, $disPrice) {
	$output = '';
	if ($price) {
		if ( !empty($disPrice)) {
			$output .= '<span class="vie-price">
							<ins>
								<span class="vie-price-amount">
									<bdi>' . format_Currency($disPrice) . '<span class="tour-rice-currencySymbol">vnđ</span></bdi>
								</span>
							</ins>
							<del>
								<span class="vie-price-amount">
									<bdi>' . format_Currency($price) . '<span class="tour-rice-currencySymbol">vnđ</span></bdi>
								</span>
							</del>
						</span>';
		} else {
			$output .= '<span class="vie-price">
							<ins>
								<span class="vie-price-amount">
									<bdi>' . format_Currency($price) . '<span class="tour-rice-currencySymbol">vnđ</span></bdi>
								</span>
							</ins>
						</span>';
		}
	} else {
		$output .= '<span class="vie-price">
		<del>
			<span class="vie-price-amount">
				<bdi>Giá liên hệ!</bdi>
			</span>
		</del>
	</span>';
	}
	return $output;
}

function generate_custom_hotel_shortcodes() {
    add_action('wp', function() {
    $post_id = get_the_ID();
    if( have_rows('product_shortcode_setting', $post_id) ):
        while( have_rows('product_shortcode_setting', $post_id) ): the_row();
            $hotel_posts  = get_sub_field('select_posts');
            $shortcode_name = get_sub_field('shortcode_name');

            if ($shortcode_name && $hotel_posts) {

                $hotel_post_ids = array();

                if (is_array($hotel_posts) && !empty($hotel_posts)) {
                    foreach ($hotel_posts as $post) {
                        if (isset($post->ID)) {
                            $hotel_post_ids[] = $post->ID;
                        }
                    }
                }

                add_shortcode($shortcode_name, function() use ($hotel_post_ids) {
                    ob_start();

                    $args = array(
                        'post_type' => 'hotel',
                        'post__in' => $hotel_post_ids,
                        'posts_per_page' => -1
                    );

                    $query = new WP_Query($args);
                    hotel_content_loop_params($query); 

                    return ob_get_clean();
                });
            } else {
                error_log('Shortcode name or hotel post is missing.');
            }
        endwhile;
    else:
        error_log('No rows found in product_shortcode_setting.');
    endif;
    });
}
add_action('wp_loaded', 'generate_custom_hotel_shortcodes');
// add function tour_content_sidebar
if ( ! function_exists( 'tour_content_sidebar' ) ) {
	function tour_content_sidebar(){ ?>
<div class="single-tour-sidebar">
    <div class="single_tour_sidebar-post-tour tour-hot">
        <?php
				$single_tour_sidbar = get_field('single_tour_sidbar','option');
				if( $single_tour_sidbar ): ?>
        <ul class="sidebar_list-post">
            <?php foreach( $single_tour_sidbar as $post ):  ?>
            <li>
                <a href="<?php the_permalink($post->ID); ?>" class="block">
                    <div class="tour-wrapper">
                        <span class="tour-bage">Hot tour</span>
                        <div class="tour-thumbnail">
                            <?php echo get_the_post_thumbnail($post->ID ,'large'); ?>
                        </div>
                        <div class="tour-inf">
                            <h5 class="tour-title"><?php echo get_the_title($post->ID); ?></h5>
                            <?php 
											$tour_price = get_field('tour_price',$post->ID);
											$tour_price_discount = get_field('tour_price_discount',$post->ID);
											if ( $tour_price || $tour_price_discount) {
												if ( !empty($tour_price) ) {
													if ( !empty($tour_price_discount) ) { ?>
                            <span class="tour-price">
                                <ins>
                                    <span class="tour-price-amount">
                                        <bdi><?php echo format_Currency($tour_price_discount); ?><span
                                                class="tour-rice-currencySymbol">vnđ</span></bdi>
                                    </span>
                                </ins>
                                <del>
                                    <span class="tour-price-amount">
                                        <bdi><?php echo format_Currency($tour_price); ?><span
                                                class="tour-rice-currencySymbol">vnđ</span></bdi>
                                    </span>
                                </del>
                            </span>
                            <?php 
													} else  { ?>
                            <span class="tour-price">
                                <ins>
                                    <span class="tour-price-amount">
                                        <bdi>
                                            <?php 
																			echo format_Currency($tour_price);
																		?>
                                            <span class="tour-rice-currencySymbol">vnđ</span>
                                        </bdi>
                                    </span>
                                </ins>
                            </span>
                            <?php
													}
												} else { 
												
												} 
											} else { ?>
                            <span class="tour-price">
                                <ins>
                                    <span class="tour-price-amount">
                                        <bdi>Giá liên hệ!</bdi>
                                    </span>
                                </ins>
                            </span>
                            <?php
											} ?>
                        </div>

                    </div>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php endif; ?>
    </div>
</div>
<?php }
}
// add shortcode hotel_archive_search_form
function hotel_archive_filter_form_shortcode() {
	ob_start(); ?>
<div class="hotel_archive_search_form-inner">
    <form method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <div class="hotel_archive_search-col">
            <div class="hotel_filter-lable-icon"><i class="fa-solid fa-hotel"></i></div>
            <input type="text" name="s" id="keyword" value="<?php echo esc_attr(get_search_query()); ?>"
                placeholder="Tên khách sạn" />
        </div>

        <div class="hotel_archive_search-col">
            <div class="hotel_filter-lable-icon"><i class="fa-solid fa-location-dot"></i></div>
            <!-- <img src="<?php //echo get_stylesheet_directory_uri() . '/modules/hotel/assets/images/map-xanh.png'; ?>" alt="" class="hotel_filter-lable-icon"> -->
            <?php
					$locations = get_terms(array(
						'taxonomy' => 'hotel-location',
						'hide_empty' => false,
						'orderby' => 'name',
						'order' => 'DESC', 
					));
		
					if (!is_wp_error($locations) && !empty($locations)) {
						echo '<select name="hotel-location">';
						echo '<option value="">Chọn địa điểm</option>';
						foreach ($locations as $location) {
							$selected = (isset($_GET['hotel-location']) && $_GET['hotel-location'] === $location->slug) ? 'selected' : '';
							echo '<option value="' . esc_attr($location->slug) . '" ' . $selected . '>' . esc_html($location->name) . '</option>';
						}
						echo '</select>';
					}
				?>
        </div>

        <div class="hotel_archive_search-col">
            <div class="hotel_filter-lable-icon"><i class="fa-solid fa-hotel"></i></div>
            <!-- <img src="<?php //echo get_stylesheet_directory_uri() . '/modules/hotel/assets/images/map-xanh.png'; ?>" alt="" class="hotel_filter-lable-icon"> -->

            <?php
					$durations = get_terms(array(
						'taxonomy' => 'hotel-rank',
						'hide_empty' => false,
						'orderby' => 'name',
						'order' => 'DESC', 
					));
		
					if (!is_wp_error($durations) && !empty($durations)) {
						echo '<select name="hotel-rank">';
						echo '<option value=""> Hạng sao</option>';
						foreach ($durations as $duration) {
							$selected = (isset($_GET['hotel-rank']) && $_GET['hotel-rank'] === $duration->slug) ? 'selected' : '';
							echo '<option value="' . esc_attr($duration->slug) . '" ' . $selected . '>' . esc_html($duration->name) . '</option>';
						}
						echo '</select>';
					}
				?>
        </div>

        <div class="hotel_archive_search-col ic-submit">
            <div class="hotel_filter-lable-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
            <input type="submit" value="Tìm khách sạn" />
        </div>
    </form>
</div>
<?php
	return ob_get_clean();
}
add_shortcode('hotel_archive_search_form', 'hotel_archive_filter_form_shortcode');
// add hotel-deal-hot
if ( ! function_exists( 'hotel_deal_hot' ) ) {
	function hotel_deal_hot(){
		$sl_hotel_hot_deal = get_field('sl_hotel_hot_deal', 'option'); ?>
<ul class="lists-hotel-deal-hot flex flex-x">
    <?php
			foreach($sl_hotel_hot_deal as $hotel){
				$post_title = get_the_title($hotel);
				$post_permalink = get_the_permalink($hotel);
				$rank_terms = get_the_terms($hotel, 'hotel-rank');
				$hotel_address = get_field('hotel_address', $hotel);
				$hotel_score = get_field('score', $hotel);
				$hotel_rank = get_field('rank', $hotel);
				$hotel_price = get_field('hotel_price', $hotel);
				$hotel_price_discount = get_field('hotel_price_discount', $hotel); ?>
    <li class="hotel_item flex">
        <div class="hotel_item-wrapper flex">
            <div class="hotel_item-head">
                <a href="<?php echo $post_permalink; ?>" class="hotel_item-head-link block relative">
                    <img src="<?php echo get_the_post_thumbnail_url($hotel, 'full'); ?>" class="hotel-feature-image"
                        alt="img">
                    <span class="hotel_item-bage">Hot Deal</span>
                </a>
            </div>
            <div class="hotel_item-body flex flex-column">
                <h3 class="title">
                    <a href="<?= esc_url($post_permalink); ?>"><?= esc_html($post_title); ?></a>
                </h3>
                <div class="summary">
                    <?php
									if ($rank_terms && !is_wp_error($rank_terms)) { ?>
                    <div class="standard">
                        <span class="text">Tiêu chuẩn <?php echo esc_html($rank_terms[0]->name); ?></span>
                    </div>
                    <?php }
								?>
                    <?php if($hotel_address) { echo '<div class="address"><i class="fa-solid fa-location-dot"></i> '. $hotel_address .'</div>'; } ?>
                    <div class="rating flex flex-x align-center">
                        <div class="score">
                            <span class="tag"><?php echo $hotel_score; ?> <i class="fa-solid fa-star"></i></span>
                            <span class="text"><?php echo $hotel_rank; ?></span>
                        </div>
                        <?php 
										$postID = get_the_ID();
										$ratingInfo = getPostRatingInfo($postID);
										echo "<div class='number'>" . $ratingInfo["rating_count"] . " đánh giá</div>";
									?>
                    </div>
                </div>
                <?php if($hotel_price || $hotel_price_discount){
								echo '<div class="group-price flex flex-x align-center justify-space-between">';
								if($hotel_price_discount){
									echo '<div class="price-sale">'. number_format($hotel_price_discount, 0, ',', '.') .'</div>';
								}
								if($hotel_price){
									echo '<div class="price-main">'. number_format($hotel_price, 0, ',', '.') .'</div>';
								}
								echo '</div>';
							} ?>
                <a href="<?= esc_url($post_permalink); ?>"
                    class="btn-main ml-auto mr-auto"><?php echo __('Nhận Deal Ngay', TEXT_DOMAIN) ?></a>
            </div>
        </div>
    </li>
    <?php } ?>
</ul>
<?php
	}
}
// add shortcode banner taxonomy-hotel
function add_banner_taxonomy_hotel() {
	ob_start();
	$banner_image_hotel = get_field('banner_image_hotel', 'option');
	$link_banner_hotel = get_field('link_banner_hotel', 'option'); ?>
<section class="section hotel-banner relative section-padding">
    <div class="container">
        <?php if($link_banner_hotel){
				echo '<a href="'. $link_banner_hotel .'" class="block">';
			} else {
				echo '<div class="relative">';
			} if($banner_image_hotel){
				echo '<img src="'. $banner_image_hotel .'">';
			}
			if($link_banner_hotel){
			echo '</a>';
			} else {
				echo '</div>';
			} ?>
    </div>
</section>
<?php
	return ob_get_clean();
}
add_shortcode('add_banner_taxonomy_hotel', 'add_banner_taxonomy_hotel');
// add shortcode tuyen-xe taxonomy-hotel
function add_route_taxonomy_hotel() {
	ob_start();
	$title_route_hotel = get_field('title_route_hotel', 'option');
	$lists_route_hotel = get_field('lists_route_hotel', 'option'); ?>
<section class="section hotel-route home-popular relative section-padding">
    <div class="container">
        <h2 class="heading-title text-center"><?php echo $title_route_hotel; ?></h2>
        <?php if($lists_route_hotel) {
				echo '<ul class="wrapper">';
				foreach($lists_route_hotel as $val){ ?>
        <li class="item relative">
            <a href="<?php echo $val['link'] ?>" class="cover relative">
                <div class="img relative">
                    <img src="<?php echo $val['img'] ?>" class="absolute" alt="image">
                </div>
                <h3 class="title"><?php echo $val['title']; ?></h3>
            </a>
            <?php if($val['tag']){
							echo '<span class="tag-popular absolute">'. $val['tag'] .'</span>';
						} ?>
        </li>
        <?php }
				echo '</ul>';
			} ?>
    </div>
</section>

<?php
	return ob_get_clean();
}
add_shortcode('add_route_taxonomy_hotel', 'add_route_taxonomy_hotel');
// function lists hotel archive, taxonomy hotel
if ( ! function_exists( 'hotel_content_loop' ) ) {
	function hotel_content_loop(){ ?>
<ul class="list-hotel flex flex-x">
    <?php
			while ( have_posts() ) {
				the_post();
				$rank_terms = get_the_terms(get_the_ID(), 'hotel-rank');
				$combo_text = get_field('combo_text', get_the_ID());
				$hotel_score = get_field('score', get_the_ID());
				$hotel_rank = get_field('rank', get_the_ID()); ?>
    <li class="hotel_item">
        <div class="hotel_item-wrapper">
            <div class="hotel_item-inner">
                <div class="hotel_item-head">
                    <a href="<?php echo get_permalink(); ?>" class="hotel_item-head-link block relative">
                        <?php the_post_thumbnail('large', ['class' => 'hotel-feature-image']) ?>
                        <span class="hotel_item-bage">
                            <?php 
											if ( !empty( get_field('hotel_tag', get_the_ID()))) {
												echo get_field('hotel_tag', get_the_ID());
											}else {
												echo __('Hot','vie-hotel'); 
											}
										?>
                        </span>
                    </a>
                </div>
                <div class="hotel_item-body-wrapper">
                    <?php if(wp_is_mobile()){ ?>
                    <h3 class="hotel_item-title">
                        <a href="<?php echo get_permalink(); ?>" class="hotel-link">
                            <?php the_title( ); ?>
                        </a>
                    </h3>
                    <?php } ?>
                    <div class="hotel_item-body">
                        <div class="hotel_item-info">
                            <?php if(!wp_is_mobile()){ ?>
                            <h3 class="hotel_item-title">
                                <a href="<?php echo get_permalink(); ?>" class="hotel-link">
                                    <?php the_title( ); ?>
                                </a>
                            </h3>
                            <?php } ?>
                            <?php //if ($rank_terms && !is_wp_error($rank_terms)) { ?>
                            <!-- <div class="standard">
												<span class="text">Tiêu chuẩn <?php //echo esc_html($rank_terms[0]->name); ?></span>
											</div> -->
                            <?php //} ?>
                            <?php if($combo_text){
											echo '<div class="combo-text" style="color: '. $combo_text['color'] .'">'. $combo_text['text'] .'</div>';
										} ?>
                            <div class="hotel_item-address">
                                <?php 
												$hotel_address = get_field('hotel_address', get_the_ID());
												if ($hotel_address) {
													?>
                                <div class="hotel_single-address">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <span class="address"><?php echo $hotel_address; ?></span>
                                </div>
                                <?php
												} else {
													$hotel_location_terms = wp_get_post_terms(get_the_ID(), 'hotel-location');
													if (!empty($hotel_location_terms)) {
														$hotel_location = $hotel_location_terms[0]->name;
														?>
                                <div class="hotel_single-address">
                                    <img src="<?php echo get_stylesheet_directory_uri() . '/modules/hotel/assets/images/map-green-icon.png'; ?>"
                                        alt="" class="address-icon">
                                    <span class="address"><?php echo $hotel_location; ?></span>
                                </div>
                                <?php
													}
												}
											?>
                            </div>
                            <div class="rating flex flex-x align-center">
                                <div class="score">
                                    <span class="tag"><?php echo $hotel_score; ?> <i
                                            class="fa-solid fa-star"></i></span>
                                    <span class="text"><?php echo $hotel_rank; ?></span>
                                </div>
                                <?php 
												$postID = get_the_ID();
												$ratingInfo = getPostRatingInfo($postID);
												echo "<div class='number'>" . $ratingInfo["rating_count"] . " đánh giá</div>";
											?>
                            </div>
                            <?php if(!wp_is_mobile()){ ?>
                            <div class="hotel_item-type">
                                <span class="text">Tiện ích</span>
                                <?php
													$taxonomy_name = 'hotel-convenient';
													$hotel_links = get_hotel_type_links($taxonomy_name);
													if (!empty($hotel_links)) {
														echo $hotel_links;
													} else {
														echo '';
													}
												?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="hotel_item-foot">
                        <span class="hotel_note"><?php echo __('Thanh toán nhận ưu đãi', 'vie-hotel') ?></span>
                        <?php 
										$hotel_price = get_field('hotel_price');
										$hotel_price_discount = get_field('hotel_price_discount');
										echo display_price( $hotel_price , $hotel_price_discount );
									?>
                        <a href="<?php echo get_permalink(); ?>"
                            class="btn-main"><?php echo __('ĐẶT NGAY','vie-hotel'); ?></a>
                    </div>
                    <?php if(wp_is_mobile()){ ?>
                    <div class="hotel_item-cta">
                        <div class="hotel_item-type">
                            <span class="text">Tiện ích</span>
                            <?php
											$taxonomy_name = 'hotel-convenient';
											$hotel_links = get_hotel_type_links($taxonomy_name);
											if (!empty($hotel_links)) {
												echo $hotel_links;
											} else {
												echo '';
											}
										?>
                        </div>
                        <a href="<?php echo get_permalink(); ?>"
                            class="btn-main"><?php echo __('ĐẶT NGAY','vie-hotel'); ?></a>
                    </div>
                    <?php } ?>

                </div>
            </div>
        </div>
    </li>
    <?php } ?>
</ul>
<?php
	}
}
// add hotel-location order by rank function
if ( ! function_exists( 'hotel_item_template' ) ) {
    function hotel_item_template(){ 
        // Lấy dữ liệu bài viết hiện tại trong vòng lặp
        $post_id = get_the_ID();
        $rank_terms = get_the_terms($post_id, 'hotel-rank');
        $combo_text = get_field('combo_text', $post_id);
        $hotel_score = get_field('score', $post_id);
        $hotel_rank = get_field('rank', $post_id);
        ?>
<li class="hotel_item">
    <div class="hotel_item-wrapper">
        <div class="hotel_item-inner">
            <div class="hotel_item-head">
                <a href="<?php echo get_permalink(); ?>" class="hotel_item-head-link block relative">
                    <?php the_post_thumbnail('large', ['class' => 'hotel-feature-image']) ?>
                    <!-- <span class="hotel_item-bage">
                        <?php 
                            //echo __('2N1Đ - XE LIMOUSINE - ĐÓN TẬN NHÀ', TEXT_DOMAIN);
                            // if ( !empty( get_field('hotel_tag', get_the_ID()))) {
                            //     echo get_field('hotel_tag', get_the_ID());
                            // } else {
                            //     echo __('Hot','vie-hotel'); 
                            // }
                            ?>
                    </span> -->
                    <span class="hotel_item-bage">
                        <?php 
                            echo '<div class="text-sm">' . __('2N1Đ - XE LIMOUSINE', TEXT_DOMAIN) . '</div>';
                            echo '<div class="text-lg">' . __('ĐÓN TẬN NHÀ', TEXT_DOMAIN) . '</div>';
                        ?>
                    </span>
                </a>
            </div>
            <div class="hotel_item-body-wrapper">
                <?php if(wp_is_mobile()){ ?>
                <h3 class="hotel_item-title">
                    <a href="<?php echo get_permalink(); ?>" class="hotel-link">
                        <?php the_title( ); ?>
                    </a>
                </h3>
                <?php } ?>
                <div class="hotel_item-body">
                    <div class="hotel_item-info">
                        <?php if(!wp_is_mobile()){ ?>
                        <h3 class="hotel_item-title">
                            <a href="<?php echo get_permalink(); ?>" class="hotel-link">
                                <?php the_title( ); ?>
                            </a>
                        </h3>
                        <?php } ?>
                        <?php //if ($rank_terms && !is_wp_error($rank_terms)) { ?>
                        <!-- <div class="standard">
												<span class="text">Tiêu chuẩn <?php //echo esc_html($rank_terms[0]->name); ?></span>
											</div> -->
                        <?php //} ?>
                        <?php if($combo_text){
											echo '<div class="combo-text" style="color: '. $combo_text['color'] .'">'. $combo_text['text'] .'</div>';
										} ?>
                        <div class="hotel_item-address">
                            <?php 
												$hotel_address = get_field('hotel_address', get_the_ID());
												if ($hotel_address) {
													?>
                            <div class="hotel_single-address">
                                <i class="fa-solid fa-location-dot"></i>
                                <span class="address"><?php echo $hotel_address; ?></span>
                            </div>
                            <?php
												} else {
													$hotel_location_terms = wp_get_post_terms(get_the_ID(), 'hotel-location');
													if (!empty($hotel_location_terms)) {
														$hotel_location = $hotel_location_terms[0]->name;
														?>
                            <div class="hotel_single-address">
                                <img src="<?php echo get_stylesheet_directory_uri() . '/modules/hotel/assets/images/map-green-icon.png'; ?>"
                                    alt="" class="address-icon">
                                <span class="address"><?php echo $hotel_location; ?></span>
                            </div>
                            <?php
													}
												}
											?>
                        </div>
                        <div class="rating flex flex-x align-center">
                            <div class="score">
                                <span class="tag"><?php echo $hotel_score; ?> <i class="fa-solid fa-star"></i></span>
                                <span class="text"><?php echo $hotel_rank; ?></span>
                            </div>
                            <?php 
												$postID = get_the_ID();
												$ratingInfo = getPostRatingInfo($postID);
												echo "<div class='number'>" . $ratingInfo["rating_count"] . " đánh giá</div>";
											?>
                        </div>
                        <?php if(!wp_is_mobile()){ ?>
                        <div class="hotel_item-type">
                            <span class="text">Tiện ích</span>
                            <?php
													$taxonomy_name = 'hotel-convenient';
													$hotel_links = get_hotel_type_links($taxonomy_name);
													if (!empty($hotel_links)) {
														echo $hotel_links;
													} else {
														echo '';
													}
												?>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="hotel_item-foot">
                    <span class="hotel_note"><?php echo __('Thanh toán nhận ưu đãi', 'vie-hotel') ?></span>
                    <?php 
										$hotel_price = get_field('hotel_price');
										$hotel_price_discount = get_field('hotel_price_discount');
										echo display_price( $hotel_price , $hotel_price_discount );
									?>
                    <a href="<?php echo get_permalink(); ?>"
                        class="btn-main"><?php echo __('ĐẶT NGAY','vie-hotel'); ?></a>
                </div>
                <?php if(wp_is_mobile()){ ?>
                <div class="hotel_item-cta">
                    <div class="hotel_item-type">
                        <span class="text">Tiện ích</span>
                        <?php
											$taxonomy_name = 'hotel-convenient';
											$hotel_links = get_hotel_type_links($taxonomy_name);
											if (!empty($hotel_links)) {
												echo $hotel_links;
											} else {
												echo '';
											}
										?>
                    </div>
                    <a href="<?php echo get_permalink(); ?>"
                        class="btn-main"><?php echo __('ĐẶT NGAY','vie-hotel'); ?></a>
                </div>
                <?php } ?>

            </div>
        </div>
    </div>
</li>
<?php 
    }
}
// add H1 is title rank math SEO
add_action( 'get_header', function () {
	ob_start( function ( $html ) {
		if ( is_singular() && class_exists( 'RankMath\Helper' ) ) {
			global $post;
			if ( $post ) {
				$seo_title = get_post_meta( $post->ID, 'rank_math_title', true );
				if ( ! empty( $seo_title ) ) {
					$title = RankMath\Helper::replace_vars( $seo_title, $post );
				} else {
					$title = get_the_title( $post );
				}
				$html = preg_replace( '/<body[^>]*>/', '$0<h1 class="seo-title-h1 hidden" style="text-align:center;padding:20px 0;">' . esc_html( $title ) . '</h1>', $html, 1 );
			}
		}
		return $html;
	});
});

// add shortcode form tour
function tour_filter_form_shortcode() {
	ob_start(); ?>
<div class="tour_filter_form-wrapper">
    <div class="tour_filter_form-inner">
        <form method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <div class="tour_filter-col">
                <div class="tour_filter-label">
                    <i class="fa-solid fa-location-dot"></i>
                    <label for="keyword">Nhập từ khóa:</label>
                </div>
                <input type="text" name="s" id="keyword" value="<?php echo esc_attr(get_search_query()); ?>"
                    placeholder="Bạn muốn đến đâu?" />
            </div>

            <div class="tour_filter-col">
                <div class="tour_filter-label">
                    <i class="fa-solid fa-location-dot"></i>
                    <label for="tour-location">Địa điểm:</label>
                </div>
                <?php
						$locations = get_terms(array(
							'taxonomy' => 'tour-location',
							'hide_empty' => false,
						));
			
						if (!is_wp_error($locations) && !empty($locations)) {
							echo '<select name="tour-location">';
							echo '<option value="">Chọn địa điểm</option>';
							foreach ($locations as $location) {
								$selected = (isset($_GET['tour-location']) && $_GET['tour-location'] === $location->slug) ? 'selected' : '';
								echo '<option value="' . esc_attr($location->slug) . '" ' . $selected . '>' . esc_html($location->name) . '</option>';
							}
							echo '</select>';
						}
						?>
            </div>

            <div class="tour_filter-col">
                <div class="tour_filter-label">
                    <i class="fa-solid fa-calendar-days"></i>
                    <label for="tour-duration">Thời gian:</label>
                </div>
                <?php
						$durations = get_terms(array(
							'taxonomy' => 'tour-duration',
							'hide_empty' => false,
						));
			
						if (!is_wp_error($durations) && !empty($durations)) {
							echo '<select name="tour-duration">';
							echo '<option value="">Chọn thời gian</option>';
							foreach ($durations as $duration) {
								$selected = (isset($_GET['tour-duration']) && $_GET['tour-duration'] === $duration->slug) ? 'selected' : '';
								echo '<option value="' . esc_attr($duration->slug) . '" ' . $selected . '>' . esc_html($duration->name) . '</option>';
							}
							echo '</select>';
						}
						?>
            </div>
            <div class="tour_filter-col">
                <input type="submit" value="Tìm Tour" />
            </div>
        </form>
    </div>
</div>
<?php

	return ob_get_clean();
}
add_shortcode('tour_filter_form', 'tour_filter_form_shortcode');

function formatCurrency($amount) {
	$formattedAmount = number_format($amount, 0, ',', '.');
	return $formattedAmount;
}

function calculatePercentage($originalPrice, $discountedPrice) {
	if ($originalPrice == 0) {
		return 0; 
	}
	$percentage = (($originalPrice - $discountedPrice) / $originalPrice) * 100;
	return $percentage;
}

// add tour
if ( ! function_exists( 'tour_content_loop' ) ) {
	function tour_content_loop(){
		$hotline_thue_xe = get_field('hotline_thue_xe','option');
		$zalo = get_field('zalo','option'); ?>
<ul class="list-tour">
    <?php
			while ( have_posts() ) {
				the_post();
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
                                <bdi><?php echo format_Currency($tour_price_discount); ?><span
                                        class="tour-rice-currencySymbol">vnđ</span></bdi>
                            </span>
                        </ins>
                        <del>
                            <span class="tour-price-amount">
                                <bdi><?php echo format_Currency($tour_price); ?><span
                                        class="tour-rice-currencySymbol">vnđ</span></bdi>
                            </span>
                        </del>
                    </span>
                    <span class="tour-dis-per">
                        <?php
															$percentage = tour_calculatePercentage(floatval($tour_price), floatval($tour_price_discount));
															$formattedPercentage = number_format($percentage, 0);
															echo $formattedPercentage . '%';
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
																		echo format_Currency($tour_price);
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
                        <a href="<?php echo get_permalink(); ?>" class="tour-link">
                            <h3 class="tour_item-title"><?php the_title( ); ?></h3>
                        </a>
                    </div>
                    <div class="tour_item-info">
                        <?php 
										if (  have_rows('tour_transport_repeat') ||  get_field('tour_time_booking')  ){
											?>
                        <div class="tour_item-info-meta">
                            <span class="tour_time_booking"><img
                                    src="<?php echo get_stylesheet_directory_uri().'/assets/images/time-icon.png'; ?>"
                                    alt="" class="time-icon"></i> <?php echo get_field('tour_time_booking'); ?></span>
                            <div class="tour-transport">
                                <?php 
															if( have_rows('tour_transport_repeat') ){
																while( have_rows('tour_transport_repeat') ) {
																	the_row();
																	$tour_transport_icon = get_sub_field('tour_transport_icon');
																	?>
                                <img src="<?php echo $tour_transport_icon['url'] ?>" alt="" class="tour_transport-icon">
                                <?php
																} 
															}
														?>
                            </div>
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
                            <img src="<?php echo get_template_directory_uri() . '/resources/img/ic-zalo.png'; ?>"
                                alt="">
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </li>
    <?php
			} ?>
</ul>
<?php
	}
}

// add vxr
// function child_theme_script() {
// 	wp_register_script( 'vexere_auto_complete','https://static.vexere.com/webnx/prod/autocomplete2/vxrAutocompleteSearch.1.0.1.js', true ); 
// 	wp_enqueue_script( 'vexere_auto_complete' );
// }
// add_action( 'wp_enqueue_scripts', 'child_theme_script', PHP_INT_MAX );

function child_theme_script_lazy_safely() {
	?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    let vexereLoaded = false;

    function loadVexereAutoComplete() {
        if (vexereLoaded) return;
        vexereLoaded = true;

        let s = document.createElement("script");
        s.src = "https://static.vexere.com/webnx/prod/autocomplete2/vxrAutocompleteSearch.1.0.1.js";
        s.async = true;
        document.body.appendChild(s);
    }

    // Lazy load khi user tương tác
    window.addEventListener("scroll", loadVexereAutoComplete, {
        once: true
    });
    window.addEventListener("click", loadVexereAutoComplete, {
        once: true
    });
    window.addEventListener("touchstart", loadVexereAutoComplete, {
        once: true
    });
});
</script>
<?php
}
add_action( 'wp_footer', 'child_theme_script_lazy_safely', 100 );

// edit canonical category when has panigation - rankmath
add_filter( 'rank_math/frontend/canonical', function( $canonical ) {
    if ( is_paged() && is_category() ) {
        $canonical = get_category_link( get_queried_object_id() );
    } elseif ( is_paged() && is_post_type_archive() ) {
        $canonical = get_post_type_archive_link( get_post_type() );
    } elseif ( is_paged() && is_home() ) {
        $canonical = home_url();
    }
    return $canonical;
});

// Function gioi han upload file anh duoi 300KB va sua thong bao trong media thành 300KB
function custom_upload_size_limit( $size ) {
    return 300 * 1024; // 300KB
}
add_filter( 'upload_size_limit', 'custom_upload_size_limit' );
// khong cho tu dong them tel: khi dinh dang la sdt
add_action('wp_head', function () {
	echo '<meta name="format-detection" content="telephone=no">';
});