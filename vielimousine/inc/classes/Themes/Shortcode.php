<?php

namespace HD\Themes;

use HD\Helper;
use HD\Utilities\Traits\Singleton;

\defined( 'ABSPATH' ) || die;

/**
 * Shortcode Class
 *
 * @author Vie
 */
final class Shortcode {
	use Singleton;

	// --------------------------------------------------

	/**
	 * @return void
	 */
	private function init(): void {
		$shortcodes = [
			'safe_mail'         => [ $this, 'safe_mail' ],
			'site_logo'         => [ $this, 'site_logo' ],
			'menu_logo'         => [ $this, 'menu_logo' ],
			'inline_search'     => [ $this, 'inline_search' ],
			'dropdown_search'   => [ $this, 'dropdown_search' ],
			'off_canvas_button' => [ $this, 'off_canvas_button' ],
			'horizontal_menu'   => [ $this, 'horizontal_menu' ],
			'vertical_menu'     => [ $this, 'vertical_menu' ],
			'posts'             => [ $this, 'posts' ],
			'form_booking'             => [ $this, 'form_booking' ],
			'form_thue_xe'             => [ $this, 'form_thue_xe' ],
			'form_booking_hotel'             => [ $this, 'form_booking_hotel' ],
			'form_strong_vn'             => [ $this, 'form_strong_vn' ],
			'form_dk_thanh_vien'             => [ $this, 'form_dk_thanh_vien' ],
			'section_partner'             => [ $this, 'section_partner' ],
			'main_faq'             => [ $this, 'main_faq' ],
			
			
		];

		foreach ( $shortcodes as $shortcode => $function ) {
			add_shortcode( $shortcode, $function );
		}
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function safe_mail( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'title' => '',
				'email' => '',
				'class' => '',
				'id'    => Helper::escAttr( uniqid( 'mail-', false ) ),
			],
			$atts,
			'safe_mail'
		);

		$attributes['title'] = $atts['title'] ? Helper::escAttr( $atts['title'] ) : Helper::escAttr( $atts['email'] );
		$attributes['id']    = $atts['id'] ? Helper::escAttr( $atts['id'] ) : Helper::escAttr( uniqid( 'mail-', false ) );

		if ( $atts['class'] ) {
			$attributes['class'] = Helper::escAttr( $atts['class'] );
		}

		return Helper::safeMailTo( $atts['email'], $atts['title'], $attributes );
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return false|string|null
	 */
	public function posts( array $atts = [] ): false|string|null {
		$default_atts = [
			'post_type'        => 'post',
			'term_ids'         => '',
			'taxonomy'         => 'category',
			'include_children' => false,
			'posts_per_page'   => 12,

			'limit_time'    => '',
			'wrapper_tag'   => '',
			'wrapper_class' => '',

			'show' => [
				'title_tag'      => 'p',
				'thumbnail'      => true,
				'thumbnail_size' => 'medium',
				'scale'          => false,
				'time'           => true,
				'term'           => true,
				'desc'           => true,
				'view_more'      => true,
			],
		];

		$atts = shortcode_atts(
			$default_atts,
			$atts,
			'posts'
		);

		$term_ids         = $atts['term_ids'] ?: [];
		$posts_per_page   = $atts['posts_per_page'] ? absint( $atts['posts_per_page'] ) : Helper::getOption( 'posts_per_page' );
		$include_children = Helper::toBool( $atts['include_children'] );
		$orderby          = [ 'date' => 'DESC' ];
		$strtotime_str    = $atts['limit_time'] ? Helper::toString( $atts['limit_time'] ) : false;

		$r = Helper::queryByTerms(
			$term_ids,
			$atts['post_type'],
			$atts['taxonomy'],
			$include_children,
			$posts_per_page,
			$orderby,
			[],
			$strtotime_str
		);

		if ( ! $r ) {
			return null;
		}

		$wrapper_open  = $atts['wrapper'] ? '<' . $atts['wrapper'] . ' class="' . $atts['wrapper_class'] . '">' : '';
		$wrapper_close = $atts['wrapper'] ? '</' . $atts['wrapper'] . '>' : '';

		ob_start();
		$i = 0;

		// Load slides loop.
		while ( $r->have_posts() && $i < $posts_per_page ) :
			$r->the_post();

			echo $wrapper_open;
			get_template_part( 'template-parts/posts/loop', null, $atts['show'] );
			echo $wrapper_close;

			++ $i;
		endwhile;
		wp_reset_postdata();

		return ob_get_clean();
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function vertical_menu( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'location'         => 'mobile-nav',
				'class'            => 'menu vertical vertical-menu mobile-menu',
				'id'               => Helper::escAttr( uniqid( 'menu-', false ) ),
				'depth'            => 4,
				'li_class'         => '',
				'li_depth_class'   => '',
				'link_class'       => '',
				'link_depth_class' => '',
			],
			$atts,
			'vertical_menu'
		);

		$location = $atts['location'] ?: 'mobile-nav';
		$class    = $atts['class'] ? Helper::escAttr( $atts['class'] ) : '';
		$depth    = $atts['depth'] ? absint( $atts['depth'] ) : 1;
		$id       = $atts['id'] ?: Helper::escAttr( uniqid( 'menu-', false ) );

		$li_class         = ! empty( $atts['li_class'] ) ? Helper::escAttr( $atts['li_class'] ) : '';
		$li_depth_class   = ! empty( $atts['li_depth_class'] ) ? Helper::escAttr( $atts['li_depth_class'] ) : '';
		$link_class       = ! empty( $atts['link_class'] ) ? Helper::escAttr( $atts['link_class'] ) : '';
		$link_depth_class = ! empty( $atts['link_depth_class'] ) ? Helper::escAttr( $atts['link_depth_class'] ) : '';

		return Helper::verticalNav( [
			'menu_id'          => $id,
			'menu_class'       => $class,
			'theme_location'   => $location,
			'depth'            => $depth,
			'li_class'         => $li_class,
			'li_depth_class'   => $li_depth_class,
			'link_class'       => $link_class,
			'link_depth_class' => $link_depth_class,
			'echo'             => false,
		] );
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function horizontal_menu( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'location'         => 'main-nav',
				'class'            => 'dropdown menu horizontal horizontal-menu desktop-menu',
				'id'               => Helper::escAttr( uniqid( 'menu-', false ) ),
				'depth'            => 4,
				'li_class'         => '',
				'li_depth_class'   => '',
				'link_class'       => '',
				'link_depth_class' => '',
			],
			$atts,
			'horizontal_menu'
		);

		$location = $atts['location'] ?: 'main-nav';
		$class    = $atts['class'] ? Helper::escAttr( $atts['class'] ) : '';
		$depth    = $atts['depth'] ? absint( $atts['depth'] ) : 1;
		$id       = $atts['id'] ?: Helper::escAttr( uniqid( 'menu-', false ) );

		$li_class         = ! empty( $atts['li_class'] ) ? Helper::escAttr( $atts['li_class'] ) : '';
		$li_depth_class   = ! empty( $atts['li_depth_class'] ) ? Helper::escAttr( $atts['li_depth_class'] ) : '';
		$link_class       = ! empty( $atts['link_class'] ) ? Helper::escAttr( $atts['link_class'] ) : '';
		$link_depth_class = ! empty( $atts['link_depth_class'] ) ? Helper::escAttr( $atts['link_depth_class'] ) : '';

		return Helper::horizontalNav( [
			'menu_id'          => $id,
			'menu_class'       => $class,
			'theme_location'   => $location,
			'depth'            => $depth,
			'li_class'         => $li_class,
			'li_depth_class'   => $li_depth_class,
			'link_class'       => $link_class,
			'link_depth_class' => $link_depth_class,
			'echo'             => false,
		] );
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function off_canvas_button( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'title'           => '',
				'hide_if_desktop' => 1,
				'class'           => '',
			],
			$atts,
			'off_canvas_button'
		);

		$title = $atts['title'] ?: __( 'Menu', TEXT_DOMAIN );
		$class = ! empty( $atts['hide_if_desktop'] ) ? ' !lg:hidden' : '';
		$class .= $atts['class'] ? ' ' . Helper::escAttr( $atts['class'] ) . $class : '';

		ob_start();

		?>
        <button class="menu-lines" type="button" data-open="offCanvasMenu" aria-label="button">
            <span class="menu-txt"><?= $title ?></span>
            <span class="line">
				<span class="line-1"></span>
				<span class="line-2"></span>
				<span class="line-3"></span>
			</span>
        </button>
		<?php

		return '<div class="off-canvas-content' . $class . '" data-off-canvas-content>' . ob_get_clean() . '</div>';
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function menu_logo( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'heading' => 'h1',
				'class'   => 'logo',
			],
			$atts,
			'menu_logo'
		);

		return Helper::siteTitleOrLogo( false, $atts['heading'], $atts['class'] );
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function site_logo( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'theme' => 'default',
				'class' => '',
			],
			$atts,
			'site_logo'
		);

		return Helper::siteLogo( $atts['theme'], $atts['class'] );
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function inline_search( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'title'       => '',
				'placeholder' => '',
				'class'       => '',
				'id'          => Helper::escAttr( uniqid( 'search-', false ) ),
			],
			$atts,
			'inline_search'
		);

		$title             = $atts['title'] ?: '';
		$title_for         = __( 'Search', TEXT_DOMAIN );
		$placeholder_title = $atts['placeholder'] ?: __( 'Bạn muốn tìm gì...', TEXT_DOMAIN );
		$id                = $atts['id'] ? Helper::escAttr( $atts['id'] ) : Helper::escAttr( uniqid( 'search-', false ) );
		$class             = $atts['class'] ? ' ' . Helper::escAttr( $atts['class'] ) : '';

		ob_start();

		?>
        <form action="<?= Helper::home() ?>" class="frm-search" method="get" accept-charset="UTF-8" data-abide novalidate>
            <label for="<?= $id ?>" class="screen-reader-text"><?= $title_for ?></label>
            <input id="<?= $id ?>" required pattern="^(.*\S+.*)$" type="search" autocomplete="off" name="s" value="<?= get_search_query() ?>" placeholder="<?= $placeholder_title; ?>">
            <button type="submit" data-fa="" aria-label="Search"><span><?= $title ?></span></button>
			<?php
			if ( Helper::isWoocommerceActive() ) : ?>
            <input type="hidden" name="post_type" value="product">
			<?php
			endif; ?>
        </form>
		<?php

		return '<div class="inline-search' . $class . '">' . ob_get_clean() . '</div>';
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function dropdown_search( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'title' => '',
				'class' => '',
				'id'    => Helper::escAttr( uniqid( 'search-', false ) ),
			],
			$atts,
			'dropdown_search'
		);

		$title             = $atts['title'] ?: __( 'Search', TEXT_DOMAIN );
		$title_for         = __( 'Search for', TEXT_DOMAIN );
		$placeholder_title = Helper::escAttr( __( 'Tìm kiếm ...', TEXT_DOMAIN ) );
		$close_title       = __( 'Close', TEXT_DOMAIN );
		$id                = $atts['id'] ? Helper::escAttr( $atts['id'] ) : Helper::escAttr( uniqid( 'search-', false ) );
		$class             = $atts['class'] ? ' ' . Helper::escAttr( $atts['class'] ) : '';

		ob_start();

		?>
        <a class="trigger-s" title="<?= Helper::escAttr( $title ) ?>" href="javascript:;" data-toggle="dropdown-<?= $id ?>" data-fa=""><span><?= $title ?></span></a>
        <div role="search" class="dropdown-pane" id="dropdown-<?= $id ?>" data-dropdown data-auto-focus="true">
            <form action="<?= Helper::home() ?>" class="frm-search" method="get" accept-charset="UTF-8" data-abide novalidate>
                <div class="frm-container">
                    <label for="<?= $id ?>" class="screen-reader-text"><?= $title_for ?></label>
                    <input id="<?= $id ?>" required pattern="^(.*\S+.*)$" type="search" name="s" value="<?= get_search_query() ?>" placeholder="<?= $placeholder_title ?>">
                    <button class="btn-s" type="submit" data-fa="" aria-label="Search"><span><?= $title ?></span></button>
                    <button class="trigger-s-close" type="button" data-fa="" aria-label="Close"><span><?= $close_title ?></span></button>
                </div>
				<?php
				if ( Helper::isWoocommerceActive() ) : ?>
                    <input type="hidden" name="post_type" value="product">
				<?php
				endif; ?>
            </form>
        </div>
		<?php

		return '<div class="dropdown-search' . $class . '">' . ob_get_clean() . '</div>';
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function form_booking( array $atts = [] ): string {
		$atts = shortcode_atts(
			$atts,
			'form_booking'
		);
		$img_form = get_field('img_form','option');
		$title_form = get_field('title_form','option');
		$all_rating = get_field('all_rating','option');
		$sl_form = get_field('sl_form_booking','option');
		ob_start();
		?>
		<div class="wrapper flex flex-x">
			<div class="cell cell-img flex align-center">
				<?php if($img_form) {
					echo '<img src="'. $img_form .'">';
				} ?>
			</div>
			<div class="cell cell-content">
				<?php if($title_form) { echo '<p class="title text-center">'. $title_form .'</p>'; } ?>
				<div class="group-inf flex flex-x gap-30 justify-center align-center">
					<?php if($all_rating){
						echo '<div class="item rating">';
						echo '<i class="fa-solid fa-star"></i>';
						echo '<span>'. $all_rating .'</span>';
						echo '</div>';
					} ?>
					<div class="item hotline">
						<i class="fa-solid fa-phone"></i>
						<a href="tel:18001131">Hotline: 1800 1131</a>
					</div>
				</div>
				<?php if($sl_form){
					echo do_shortcode('[contact-form-7 id="' . esc_attr($sl_form) . '"]');
				} ?>
			</div>
		</div>
		<?php

		return '<div class="section-form-booking">' . ob_get_clean() . '</div>';
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function form_thue_xe( array $atts = [] ): string {
		$atts = shortcode_atts(
			$atts,
			'form_thue_xe'
		);
		$img_form_rental = get_field('img_form_rental','option');
		$title_form_rental = get_field('title_form_rental','option');
		$sl_form_booking_rental = get_field('sl_form_booking_rental','option');
		ob_start();
		?>
		<div class="wrapper flex flex-x">
			<div class="cell cell-img flex align-center">
				<?php if($img_form_rental) {
					echo '<img src="'. $img_form_rental .'">';
				} ?>
			</div>
			<div class="cell cell-content">
				<?php if($title_form_rental) { echo '<p class="title text-center">'. $title_form_rental .'</p>'; } ?>
				<?php if($sl_form_booking_rental){
					echo do_shortcode('[contact-form-7 id="' . esc_attr($sl_form_booking_rental) . '"]');
				} ?>
			</div>
		</div>
		<?php

		return '<div class="section-form-booking section-form-rental">' . ob_get_clean() . '</div>';
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function form_booking_hotel( array $atts = [] ): string {
		$atts = shortcode_atts(
			$atts,
			'form_booking_hotel'
		);
		$img_form_hotel = get_field('img_form_hotel','option');
		$title_form_hotel = get_field('title_form_hotel','option');
		$sl_form_hotel = get_field('sl_form_hotel','option');
		ob_start();
		?>
		<div class="wrapper flex flex-x">
			<div class="cell cell-img flex align-center">
				<?php if($img_form_hotel) {
					echo '<img src="'. $img_form_hotel .'">';
				} ?>
			</div>
			<div class="cell cell-content">
				<?php if($title_form_hotel) { echo '<p class="title text-center">'. $title_form_hotel .'</p>'; } ?>
				<?php if($sl_form_hotel){
					echo do_shortcode('[contact-form-7 id="' . esc_attr($sl_form_hotel) . '"]');
				} ?>
			</div>
		</div>
		<?php

		return '<div class="section-form-booking section-form-booking_hotel">' . ob_get_clean() . '</div>';
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function form_strong_vn( array $atts = [] ): string {
		$atts = shortcode_atts(
			$atts,
			'form_strong_vn'
		);
		$img_form_strong = get_field('img_form_strong','option');
		$title_form_strong = get_field('title_form_strong','option');
		$sl_form_strong = get_field('sl_form_strong','option');
		ob_start();
		?>
		<div class="wrapper flex flex-x">
			<div class="cell cell-img flex align-center">
				<?php if($img_form_strong) {
					echo '<img src="'. $img_form_strong .'">';
				} ?>
			</div>
			<div class="cell cell-content">
				<?php if($title_form_strong) { echo '<p class="title text-center">'. $title_form_strong .'</p>'; } ?>
				<?php if($sl_form_strong){
					echo do_shortcode('[contact-form-7 id="' . esc_attr($sl_form_strong) . '"]');
				} ?>
			</div>
		</div>
		<?php

		return '<div class="section-form-booking section-form-strong">' . ob_get_clean() . '</div>';
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function form_dk_thanh_vien( array $atts = [] ): string {
		$atts = shortcode_atts(
			$atts,
			'form_dk_thanh_vien'
		);
		$img_form_tv = get_field('img_form_tv','option');
		$title_form_tv = get_field('title_form_tv','option');
		$sl_form_tv = get_field('sl_form_tv','option');
		ob_start();
		?>
		<div class="wrapper flex flex-x">
			<div class="cell cell-img flex align-center">
				<?php if($img_form_tv) {
					echo '<img src="'. $img_form_tv .'">';
				} ?>
			</div>
			<div class="cell cell-content">
				<?php if($title_form_tv) { echo '<p class="title text-center">'. $title_form_tv .'</p>'; } ?>
				<?php if($sl_form_tv){
					echo do_shortcode('[contact-form-7 id="' . esc_attr($sl_form_tv) . '"]');
				} ?>
			</div>
		</div>
		<?php

		return '<div class="section-form-booking section-form-tv">' . ob_get_clean() . '</div>';
	}

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function section_partner( array $atts = [] ): string {
		$atts = shortcode_atts(
			$atts,
			'section_partner'
		);
		$title_partner = Helper::getField( 'title_partner', 'option' );
		$gallery = Helper::getField( 'gallery_partner', 'option' );
		ob_start(); ?>
		<div class="container">
			<?php if($title_partner){
				echo '<h2 class="heading-title text-center">'. $title_partner .'</h2>';
			}
		if($gallery) {
            if(!wp_is_mobile()){
                echo '<div class="gallery">';
                echo '<div class="row row-1 flex gap-30">';
                $i = 0;
                foreach($gallery as $val){
                    $i++;
                    if ($i == 7) {
                        echo '</div>';
                        echo '<div class="row row-2 flex gap-30">';
                    } ?>
                    <div class="item relative">
                        <img src="<?php echo $val; ?>" class="absolute" alt="đối tác">
                    </div>
                <?php }
                if ($i >= 7) {
                    echo '</div>';
                } else {
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<div class="gallery gallery-mb">';
                    echo '<div class="swiper-container">';
                        $_data = [
                            'autoview'   => true,
                            'loop' => true,
                            'pagination' => true,
                            'autoplay' => true,
                        ];
                        try {
                            $swiper_data = json_encode( $_data, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE );
                        } catch ( \JsonException $e ) {}
                        if ( $swiper_data ) : ?>
                        <div class="w-swiper swiper" data-options='<?= $swiper_data ?>'>
                            <div class="swiper-wrapper">
                                <?php foreach($gallery as $item){ ?>
                                    <div class="swiper-slide item relative">
                                        <img src="<?php echo $item; ?>" class="absolute" alt="đối tác">
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php }
        } ?>
		</div>
		<?php return '<section class="section home-partner section-padding relative">' . ob_get_clean() . '</section>';
	}

	// ------------------------------------------------------

	// ------------------------------------------------------

	/**
	 * @param array $atts
	 *
	 * @return string
	 */
	public function main_faq( array $atts = [] ): string {
		$atts = shortcode_atts(
			$atts,
			'main_faq'
		);
		$title_faq = Helper::getField( 'title_faq' );
		$sl_heading = Helper::getField( 'sl_heading' );
		$main_faq = Helper::getField( 'main_faq' );
		ob_start(); ?>
		<?php if($title_faq){ ?>
			<div class="heading-group text-center">
            	<<?php echo $sl_heading; ?> class="heading-title"><?php echo $title_faq; ?></<?php echo $sl_heading; ?>>
				<?php  echo '<img src="'. get_template_directory_uri() . '/resources/img/line-title-vie.png' .'">'; ?>
			</div>
        <?php }
		if($main_faq) ?>
		<ul class="lists-faq">
			<?php foreach($main_faq as $val){ ?>
				<li class="toggle-item">
					<div class="tab-title">
						<p class="title"><?php echo $val['ques']; ?></p>
					</div>
					<div class="tab-content">
						<div class="content"><?php echo $val['ans']; ?></div>
					</div>
				</li>
			<?php } ?>
		</ul>
		</div>
		<?php return '<div class="section section-faq section-padding relative">' . ob_get_clean() . '</div>';
	}

	// ------------------------------------------------------


}