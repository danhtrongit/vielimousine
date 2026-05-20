<?php
/**
 * Header hooks
 *
 * @author Gaudev
 */

use HD\Helper;
\defined( 'ABSPATH' ) || die;

// -----------------------------------------------
// wp_head
// -----------------------------------------------

add_action( 'wp_head', 'wp_head_action', 1 );

function wp_head_action(): void {
	//$meta_viewport = '<meta name="viewport" content="user-scalable=yes, width=device-width, initial-scale=1.0, maximum-scale=2.0, minimum-scale=1.0" />';
	$meta_viewport = '<meta name="viewport" content="width=device-width, initial-scale=1.0" />';
	echo apply_filters( 'meta_viewport_filter', $meta_viewport );

	if ( is_singular() && pings_open() ) {
		printf( '<link rel="pingback" href="%s" />', esc_url( get_bloginfo( 'pingback_url' ) ) );
	}
}

// -----------------------------------------------

add_action( 'wp_head', 'other_head_action', 10 );

function other_head_action(): void {
	// manifest.json
	if ( is_file( ABSPATH . 'manifest.json' ) ) {
		printf( '<link rel="manifest" href="%s" />', esc_url( home_url( 'manifest.json' ) ) );
	}

	// Theme color
	$theme_color = \HD\Helper::getThemeMod( 'theme_color_setting' );
	if ( $theme_color ) {
		printf( '<meta name="theme-color" content="%s" />', \HD\Helper::escAttr( $theme_color ) );
	}
}

// -----------------------------------------------

add_action( 'wp_head', 'external_fonts_action', 99 );

function external_fonts_action(): void { ?>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400..700&display=swap" rel="stylesheet">
	
<?php }

// -----------------------------------------------
// hd_header_before_action
// -----------------------------------------------

add_action( 'hd_header_before_action', 'skip_to_content_link_action', 2 );

function skip_to_content_link_action(): void {
	printf(
		'<a class="screen-reader-text skip-link" href="#site-content" title="%1$s">%2$s</a>',
		esc_attr__( 'Skip to content', TEXT_DOMAIN ),
		esc_html__( 'Skip to content', TEXT_DOMAIN )
	);
}

// -----------------------------------------------

add_action( 'hd_header_before_action', 'off_canvas_menu_action', 11 );

function off_canvas_menu_action(): void {
    \HD\Helper::blockTemplate( 'template-blocks/off-canvas' );
}

// -----------------------------------------------
// hd_header_action
// -----------------------------------------------

add_action( 'hd_header_action', 'construct_header_action', 10 );

function construct_header_action(): void {

	/**
	 * @see _masthead_home_seo_header - 10
	 * @see _masthead_top_header - 12
	 * @see _masthead_header - 13
	 * @see _masthead_bottom_header - 14
	 * @see _masthead_custom - 98
	 */
	do_action( 'masthead' );
}

// -----------------------------------------------

add_action( 'masthead', '_masthead_home_seo_header', 10 );

function _masthead_home_seo_header(): void {
	$home_heading = \HD\Helper::getThemeMod( 'home_heading_setting' );
	$home_heading = ! empty( $home_heading ) ? esc_html( $home_heading ) : get_bloginfo( 'name' );
	// if($home_heading){
	// 	echo apply_filters( 'home_seo_header_filter', '<h1 class="sr-only">' . $home_heading . '</h1>' );
	// }
}

// -----------------------------------------------

add_action( 'masthead', '_masthead_top_header', 12 );

function _masthead_top_header(): void {
	$m_hotline    = Helper::getField( 'm_hotline', 'option' );
	?>
	<div id="top-header">
		<div class="container flex flex-x gap align-center">
			<div class="cell cell-left">
				<ul class="top-menu flex align-center">
					<li class="relative">
						<a href="<?php echo home_url(); ?>" class="link-home">TRANG CHỦ</a>
					</li>
					<li class="menu-lang flex align-center">
						<span>NGÔN NGỮ</span>
						<?php echo Helper::doShortcode( 'gtranslate' ); ?>
					</li>
				</ul>
			</div>
			<div class="cell cell-right flex align-center">
				<i class="fa-solid fa-phone"></i>
				<?php if($m_hotline) {
					echo '<ul class="wrapper flex">';
					foreach($m_hotline as $val){
						$lang = $val['lang'] ?? 'Tiếng Việt';
						echo '<li class="item relative">';
						echo '<span class="text">'. $lang .'</span>';
						echo '<a href="tel:'. $val['phone'] .'" class="phone" rel="nofollow">'. $val['phone'] .'</a>';
						echo '</li>';
					}
					echo '</div>';
				} ?>
			</div>
		</div>
	</div>
<?php }

// -----------------------------------------------

add_action( 'masthead', '_masthead_header', 13 );

function _masthead_header(): void {
	$slogan = Helper::getField( 'slogan_header', 'option' ); ?>
	<div id="inside-header">
		<div class="container flex">
			<?php //echo Helper::doShortcode( 'off_canvas_button' ); ?>
			<div class="cell cell-left cell-logo relative">
				<?php echo Helper::siteTitleOrLogo(); ?>
			</div>
			<div class="cell cell-right">
				<?php if($slogan){
					echo '<div class="slogan">'. $slogan .'</div>';
				} ?>	
			</div>
		</div>
	</div>
<?php }

// -----------------------------------------------

add_action( 'masthead', '_masthead_bottom_header', 14 );
function _masthead_bottom_header(): void {
	//...
	?>
	<div id="bottom-header">
		<div class="container">
			<div class="cell-menu flex align-center justify-center relative">
				<nav class="nav menu-primary">
					<?php $atts = [
						'location'    => 'main-nav',
						'extra_class' => 'main-nav',
					];
					echo Helper::doShortcode( 'horizontal_menu', $atts ); ?>
				</nav>
				<div class="menu-expend">
					<i class="fa-solid fa-bars"></i>
					<i class="fa-solid fa-square-xmark"></i>
					<nav class="sub-nav absolute">
						<?php $atts = [
							'location'    => 'sub-nav',
							'extra_class' => 'sub-nav',
						];
						echo Helper::doShortcode( 'vertical_menu', $atts ); ?>
					</nav>
				</div>
			</div>
		</div>
	</div>
<?php }
// -----------------------------------------------

add_action( 'masthead', '_masthead_custom', 98 );

function _masthead_custom(): void {
	//...
}

// -----------------------------------------------
// hd_header_after_action
// -----------------------------------------------

// -----------------------------------------------
// hd_site_content_before_action
// -----------------------------------------------
