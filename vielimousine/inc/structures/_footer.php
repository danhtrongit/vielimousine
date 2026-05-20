<?php
/**
 * Footer hooks
 *
 * @author Gaudev
 */

use HD\Helper;
\defined( 'ABSPATH' ) || die;

// -----------------------------------------------
// wp_footer
// -----------------------------------------------

add_action( 'wp_footer', 'wp_footer_action', 98 );

function wp_footer_action(): void {
	if ( apply_filters( 'back_to_top_filter', true ) ) {
		echo apply_filters(
			'back_to_top_output_filter',
			sprintf(
				'<a title="%1$s" aria-label="%1$s" rel="nofollow" href="#" class="back-to-top toTop" data-scroll-speed="%2$s" data-scroll-start="%3$s">%4$s</a>',
				esc_attr__( 'Scroll back to top', TEXT_DOMAIN ),
				absint( apply_filters( 'back_to_top_scroll_speed_filter', 400 ) ),
				absint( apply_filters( 'back_to_top_scroll_start_filter', 300 ) ),
				'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24"><g fill="none"><path d="M8.47 4.22a.75.75 0 0 0 0 1.06L15.19 12l-6.72 6.72a.75.75 0 1 0 1.06 1.06l7.25-7.25a.75.75 0 0 0 0-1.06L9.53 4.22a.75.75 0 0 0-1.06 0z" fill="currentColor"></path></g></svg>'
			)
		);
	}
}

// -----------------------------------------------
// hd_footer_after_action
// -----------------------------------------------

// -----------------------------------------------
// hd_footer_action
// -----------------------------------------------

add_action( 'hd_footer_action', 'construct_footer_action', 10 );

function construct_footer_action(): void {

	/**
	 * @see _construct_footer_columns - 11
	 * @see _construct_footer_credit - 12
	 * @see _construct_footer_custom - 98
	 */
	do_action( 'construct_footer' );
}

// -----------------------------------------------

add_action( 'construct_footer', '_construct_footer_columns', 11 );

function _construct_footer_columns(): void {
	$footer_name_comp    = Helper::getField( 'm_name', 'option' );
	$footer_tax_code    = Helper::getField( 'tax_code', 'option' );
	$footer_business    = Helper::getField( 'business_registration', 'option' );
	$footer_email    = Helper::getField( 'm_email', 'option' );
	$footer_bct    = Helper::getField( 'link_bct', 'option' );
	$footer_hotline    = Helper::getField( 'm_hotline', 'option' );
	$footer_address    = Helper::getField( 'g_address', 'option' );
	$footer_qr    = Helper::getField( 'qr_ck', 'option' );
	$footer_cer    = Helper::getField( 'footer_cer', 'option' );
	?>
    <div id="footer-columns" class="footer-columns">
		<div class="container">
			<div class="row flex flex-x">
				<div class="cell footer-contact">
					<?php echo Helper::siteTitleOrLogo(); ?>
					<?php if($footer_name_comp){ ?>
						<p class="name-comp"><?php echo $footer_name_comp; ?></p>
					<?php } ?>
					<ul class="lists">
						<?php 
						if($footer_tax_code){ ?>
						<li class="item">
							<span>Mã số thuế:</span> <?php echo $footer_tax_code; ?>
						</li>	
						<?php }
						if($footer_business){ ?>
							<li class="item">
								<span>Đăng ký kinh doanh:</span> <?php echo $footer_business; ?></a>
							</li>
						<?php }
						if($footer_email){ ?>
							<li class="item">
								<a href="mailto:<?php echo $footer_email; ?>" rel="nofollow"><span>Email:</span> <?php echo $footer_email; ?></a>
							</li>
						<?php } ?>
					</ul>
					<div class="footer-social">
						<?php echo Helper::doShortcode( 'social_menu' ); ?>
					</div>
					<?php if($footer_bct){ ?>
						<a href="<?php echo $footer_bct; ?>" target="_blank" rel="nofollow" class="link-bct">
							<img src="<?php echo get_template_directory_uri() . '/resources/img/logo-thong-bao-bo-cong-thuong.png'; ?>" alt="Bộ Công Thương">
						</a>
					<?php } ?>
				</div>
				<div class="cell footer-hotline-menu">
					<div class="group-hotline">
						<p class="footer-title">HOTLINE</p>
						<?php if($footer_hotline){
							echo '<div class="list">';
							foreach($footer_hotline as $hotline){
								echo '<a href="tel:'. $hotline['phone'] .'" rel="nofollow">';
								echo '<i class="fa-solid fa-phone"></i>';
								echo '<span>'. $hotline['phone'] .'</span>';
								echo '</a>';
							}
							echo '</div>';
						} ?>
					</div>
					<div class="group-address">
						<p class="footer-title">ĐỊA CHỈ</p>
						<?php if($footer_address){
							echo '<ul class="wrapper">';
							foreach($footer_address as $val){
								echo '<li class="item">';
								echo '<a href="'. $val['link_gmap'] .'" class="address" target="_blank" rel="nofollow">';
								echo '<i class="fa-solid fa-location-dot"></i>';
								echo '<div class="text">'. $val['address'] .'</div>';
								echo '</a>';
								echo '</li>';
							}
							echo '</ul>';
						} ?>
					</div>
				</div>
				<div class="cell footer-menu">
					<p class="footer-title">LIÊN KẾT</p>
					<nav class="nav">
						<?php $atts = [
							'location'    => 'footer-nav',
							'extra_class' => 'footer-nav',
						];
						echo Helper::doShortcode( 'vertical_menu', $atts ); ?>
					</nav>
				</div>
				<div class="cell footer-other">
					<div class="group-certificate">
						<p class="footer-title">GIẤY PHÉP LỮ HÀNH QUỐC TẾ</p>
						<?php if($footer_cer){
							echo '<a href="'. $footer_cer .'" class="fcy-popup block">';
							echo '<img src="'. $footer_cer .'" alt="Chứng chỉ">';
							echo '</a>';
						} ?>
					</div>
					<div class="group-qr">
						<p class="footer-title">THÔNG TIN CHUYỂN KHOẢN</p>
						<?php if($footer_qr){
							echo '<a href="'. $footer_qr .'" class="fcy-popup block">';
							echo '<img src="'. $footer_qr .'" alt="QR">';
							echo '</a>';
						} ?>
					</div>
				</div>
			</div>
		</div>
    </div>
	<?php
}

// -----------------------------------------------

//add_action( 'construct_footer', '_construct_footer_credit', 12 );
function _construct_footer_credit(): void {
	?>
    <div id="footer-credit" class="footer-credit">
		<?php
		echo \_toggle_container_open( true, 'container' );

		$footer_credit = \HD\Helper::getThemeMod( 'footer_credit_setting' );
		$footer_credit = ! empty( $footer_credit ) ? esc_html( $footer_credit ) : '&copy; ' . date( 'Y' ) . ' ' . get_bloginfo( 'name' ) . '. ' . esc_html__( 'All rights reserved.', TEXT_DOMAIN );

		echo '<p class="copyright">';
		echo apply_filters( 'footer_credit_filter', $footer_credit );
		echo '</p>';

        //...

		echo \_toggle_container_close( true );
		?>
    </div>
	<?php
}

// -----------------------------------------------

add_action( 'construct_footer', '_construct_footer_custom', 98 );

function _construct_footer_custom(): void {
	// form popup dat ve nhanh
	$sider_title_form = get_field('sider_title_form','option');
	$sider_desc_form = get_field('sider_desc_form','option');
	$sider_sl_form = get_field('sider_sl_form','option');
	// form dat khach san
	$title_form_hotel = get_field('title_form_hotel','option');
	$desc_form_hotel = get_field('desc_form_hotel','option');
	$sl_form_single_hotel = get_field('sl_form_single_hotel','option');
	?>
	<div class="popup-content" id="popup-dang-ky-nhanh">
		<div class="sidebar-form" id="dang-ky-nhanh">
			<div class="wrapper">
				<div class="group-heading text-center">
					<p class="title-form heading-title"><?php echo $sider_title_form; ?></p>
					<div class="desc"><?php echo $sider_desc_form; ?></div>
				</div>
				<?php echo do_shortcode('[contact-form-7 id="' . esc_attr($sider_sl_form) . '"]'); ?>
			</div>
		</div>
	</div>
	<div class="popup-content" id="popup-dang-ky-khach-san">
		<div class="sidebar-form" id="dang-ky-khach-san">
			<div class="wrapper">
				<div class="single_tour_sidebar-price">
					<div class="single_tour_sidebar-price-inner">
						<p class="hotel-form-title">ĐẶT KHÁCH SẠN</p>
					</div>
				</div>
				<div class="single_tour_sidebar-form">
					<div class="single_tour_sidebar-form-inner">
						<?php if($sl_form_single_hotel){
							echo do_shortcode('[contact-form-7 id="' . esc_attr($sl_form_single_hotel) . '"]');
						} ?>
					</div>
				</div>
				<!-- <div class="group-heading text-center">
					<p class="title-form heading-title"><?php //echo $title_form_hotel; ?></p>
					<div class="desc"><?php //echo $desc_form_hotel; ?></div>
				</div>
				<?php //echo do_shortcode('[contact-form-7 id="' . esc_attr($sl_form_single_hotel) . '"]'); ?> -->
			</div>
		</div>
	</div>

	<div class="footer-contact-mb">
		<p class="title">Hỗ trợ nhanh</p>
		<div class="lists flex">
			<div class="item">
				<a href="tel:0819001131" target="_blank">
					<i class="fa-solid fa-phone"></i>
					<span class="text">Hotline</span>
				</a>
			</div>
			<div class="item">
				<a href="<?php echo get_field('zalo','option'); ?>" target="_blank">
					<img src="<?php echo get_template_directory_uri(). '/resources/img/zalo-white.png' ?>" alt="">
					<span class="text">Zalo</span>
				</a>
			</div>
			<div class="item">
				<a href="#popup-dang-ky-nhanh" class="fcy-popup">
					<i class="fa-solid fa-calendar-days"></i>
					<span class="text">Đặt vé</span>
				</a>
			</div>
		</div>
	</div>
<?php }

// -----------------------------------------------
// hd_footer_before_action
// -----------------------------------------------

// -----------------------------------------------
// hd_site_content_after_action
// -----------------------------------------------
