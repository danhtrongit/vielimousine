<?php

/**
 * The template for displaying `Page Car Rental`
 * Template Name: Page Thuê xe
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
$page_id = get_queried_object_id();
$ACF = \HD\Helper::getFields( get_the_ID() );
$car_rental_flexible = ! empty( $ACF['car_rental_flexible'] ) ? (array) $ACF['car_rental_flexible'] : false;
if ( $car_rental_flexible ) {
	foreach ( $car_rental_flexible as $section ) {
		$acf_fc_layout = $section['acf_fc_layout'] ?? '';

		if ( $acf_fc_layout ) {
			\HD\Helper::blockTemplate( 'template-parts/car-rental/' . $acf_fc_layout, $section );
		}
	}
} else {
	\HD\Helper::blockTemplate( 'template-blocks/static-page' );
}
?>

<div class="rental-general">
	
	<?php
		$title_choose = get_field('title_whychoose','option');
		$lists_choose = get_field('lists_whychoose','option');
		if($lists_choose){
			echo '<div class="rental-why-choose section-route-experience section-padding">';
			echo '<div class="container">';
			if($title_choose){
				echo '<div class="heading-group text-center">';
				echo '<h2 class="heading-title">'. $title_choose .'</h2>';
				echo '<img src="'. get_template_directory_uri() . '/resources/img/line-title-vie.png' .'">';
				echo '</div>';
			}
			echo '<ul class="wrapper">';
			foreach($lists_choose as $val){
				echo '<li class="item flex">';
				echo '<div class="cover">';
				echo '<span class="scale res ar-4-3">';
				echo '<img src="'. $val['img'] .'">';
				echo '</span>';
				echo '</div>';
				echo '<div class="content text-center flex align-center">';
				echo '<h3 class="title">'. $val['title'] .'</h3>';
				echo '<div class="desc">'. $val['content'] .'</div>';
				echo '</div>';
				echo '</li>';
			}
			echo '</ul>';
			echo '</div>';
			echo '</div>';
		}
	?>

	<?php echo do_shortcode('[section_partner]'); ?>

	<?php get_template_part('template-blocks/section-newspapers'); ?>

	<?php //get_template_part('template-blocks/section-posts'); ?>

	<?php $title_posts_rental = get_field('title_posts_rental', $page_id);
	$sl_posts_rental = get_field('sl_posts_rental', $page_id);
	if($sl_posts_rental){ ?>
	<section class="section home-posts section-padding">
		<div class="container">
			<?php if($title_posts_rental){
				echo '<h2 class="heading-title text-center">'. $title_posts_rental .'</h2>';
			} ?>
			<div class="home-posts__content">
				<?php if ( $sl_posts_rental ) : ?>
					<div class="swiper-container">
						<?php
						$_data = [
							'autoview'   => true,
							'loop' => true,
							'navigation' => true,
						];
						if ( $autoplay ) {
							$_data['autoplay'] = Helper::toBool( $autoplay );
						}
						try {
							$swiper_data = json_encode( $_data, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE );
						} catch ( \JsonException $e ) {}
						if ( $swiper_data ) : ?>
							<div class="w-swiper swiper" data-options='<?= $swiper_data ?>'>
								<div class="swiper-wrapper">
									<?php foreach ( $sl_posts_rental as $post ) :
										setup_postdata( $post );
										$post_title     = get_the_title( $post->ID );
										$post_title     = ! empty( $post_title ) ? $post_title : __( '(no title)', TEXT_DOMAIN );
										$post_thumbnail = get_the_post_thumbnail( 
											$post->ID, 
											'large', 
											['alt' => Helper::escAttr( $post_title )] 
										); ?>
										<div class="swiper-slide">
											<div class="item">
												<div class="cover">
													<span class="scale res ar-4-3">
														<?= $post_thumbnail ?>
														<a class="link-cover" href="<?= get_permalink( $post->ID ) ?>" aria-label="<?= Helper::escAttr( $post_title ) ?>"></a>
													</span>
												</div>
												<div class="content">
													<h3 class="title">
														<a href="<?= get_permalink( $post->ID ) ?>" aria-label="<?= Helper::escAttr( $post_title ) ?>"><?= $post_title ?></a>
													</h3>
													<?= Helper::loopExcerpt( $post ) ?>
													<a href="<?= get_permalink( $post->ID ) ?>" class="btn-see-more">Đọc tiếp <i class="fa-regular fa-arrow-right-long"></i></a>
												</div>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; 
					wp_reset_postdata(); ?>
				<?php echo '</div>';
			endif; ?>
		</div>    
	</section>
	<?php } ?>
	
	<?php HD\Helper::blockTemplate(
		'template-parts/car-rental/section_policy',
		[ 'page_id' => $page_id ]
	); ?>

	<?php HD\Helper::blockTemplate(
		'template-parts/car-rental/section_faq',
		[ 'page_id' => $page_id ]
	); ?>

</div>

<?php

// footer
get_footer( 'home' );