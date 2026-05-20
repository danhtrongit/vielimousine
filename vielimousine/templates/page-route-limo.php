<?php
/**
 * The template for displaying `Page Tuyến Xe`
 * Template Name: Page Tuyến Xe
 * Template Post Type: page
 *
 * @author Vie
 */
use HD\Helper;
\defined( 'ABSPATH' ) || die;

// header
get_header();

if ( have_posts() ) {
	the_post();
}

if ( post_password_required() ) {
	echo get_the_password_form();
	get_footer();

	return;
}

$ACF = \HD\Helper::getFields( get_the_ID() );
$route_flexible_top = ! empty( $ACF['route_flexible_top'] ) ? (array) $ACF['route_flexible_top'] : false;
$route_flexible_content = ! empty( $ACF['route_flexible_content'] ) ? (array) $ACF['route_flexible_content'] : false;
if ( $route_flexible_top ) {
	foreach ( $route_flexible_top as $section ) {
		$acf_fc_layout = $section['acf_fc_layout'] ?? '';
		if ( $acf_fc_layout ) {
			\HD\Helper::blockTemplate( 'template-parts/route-limo/' . $acf_fc_layout, $section );
		}
	}
} else {
	\HD\Helper::blockTemplate( 'template-blocks/static-page' );
}
?>
<div class="route-primary">
    <div class="container">
        <div class="main-wrapper flex flex-x">
            <div class="cell cell-content">
                <?php 
				if ( $route_flexible_content ) {
					foreach ( $route_flexible_content as $section_content ) {
						$acf_fc_layout = $section_content['acf_fc_layout'] ?? '';
						if ( $acf_fc_layout ) {
							\HD\Helper::blockTemplate( 'template-parts/route-limo/' . $acf_fc_layout, $section_content );
						}
					}
				} else {
					\HD\Helper::blockTemplate( 'template-blocks/static-page' );
				} ?>
            </div>
            <aside class="cell cell-sidebar">
                <?php 
				// reviews
				$list_reviews = get_field('list_reviews','option');
				// form
				$sider_title_form = get_field('sider_title_form','option');
				$sider_desc_form = get_field('sider_desc_form','option');
				$sider_sl_form = get_field('sider_sl_form','option');
				// banner promotion
				$banner_promotion = get_field('lists_banner_promotion','option');
				$lists_banner_promotion = get_field('lists_banner_promotion');
				if($list_reviews){
					echo '<div class="sidebar-reviews section-padding">';
					echo '<div class="wrapper">';
						foreach($list_reviews as $val){ ?>
                <div class="item">
                    <div class="top">
                        <?php if($val['link_reviews']){
										echo '<a href="'. $val['link_reviews'] .'" class="link" target="_blank">';
									} ?>
                        <?php if($val['name']){
										echo '<span class="name">'. $val['name'] .'</span>';
									} ?>
                        <?php if($val['job']){
										echo '<span class="job">'. $val['job'] .'</span>';
									} ?>
                        <?php if($val['link_reviews']){ echo '</a>'; } ?>
                    </div>
                    <div class="rating">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <?php if($val['cmt']){
									echo '<div class="cmt">'. $val['cmt'] .'</div>';
								} ?>
                </div>
                <?php }
					echo '</div>';
					echo '</div>';
				} ?>
                <div class="sidebar-sticky">
                    <!-- <div class="sidebar-form" id="dang-ky-nhanh">
						<div class="wrapper">
							<div class="group-heading text-center">
								<p class="title-form heading-title"><?php //echo $sider_title_form; ?></p>
								<div class="desc"><?php //echo $sider_desc_form; ?></div>
							</div>
							<?php //echo do_shortcode('[contact-form-7 id="' . esc_attr($sider_sl_form) . '"]'); ?>
						</div>
					</div> -->
                    <?php if($lists_banner_promotion){ ?>
                    <div class="sidebar-banner">
                        <ul class="wrapper">
                            <?php foreach($lists_banner_promotion as $val){
									echo '<li class="item">';
									$link = $val['link'] ?? 'javascript:void(0)';
									echo '<a href="'. $link .'" class="block" target="_blank">';
									echo '<img src="'. $val['banner'] .'">';
									echo '</a>';
									echo '</li>';
								} ?>
                        </ul>
                    </div>
                    <?php } ?>
                </div>

                <?php //HD\Helper::blockTemplate( 'template-parts/route-limo/route_form' ); ?>
            </aside>
        </div>
    </div>

    <?php 
		$title_faq = Helper::getField( 'title_faq' );
		$lists_faq = Helper::getField( 'lists_faq' );
		if($lists_faq){ ?>
    <section class="section section-faq section-padding relative" id="chinh-sach-huy-ve">
        <div class="container">
            <?php if($title_faq){
						echo '<div class="heading-group text-center">';
						echo '<h2 class="heading-title">'. $title_faq .'</h2>';
						echo '<img src="'. get_template_directory_uri() . '/resources/img/line-title-vie.png' .'">';
						echo '</div>';
					} ?>
            <ul class="lists-faq">
                <?php foreach($lists_faq as $val){ ?>
                <li class="toggle-item">
                    <div class="tab-title">
                        <p class="title"><?php echo $val['question']; ?></p>
                    </div>
                    <div class="tab-content">
                        <div class="content"><?php echo $val['answer']; ?></div>
                    </div>
                </li>
                <?php } ?>
            </ul>
        </div>
    </section>
    <?php }
	?>

    <?php 
		$banner_route = get_field('general_banner_route','option');
		$link_general_banner = get_field('link_general_banner','option');
		if($banner_route){
			echo '<div class="banner-general-route section-padding">';
			echo '<div class="container">';
			echo '<a href="'. esc_url($link_general_banner) .'" class="block">';
			echo '<img src="'. $banner_route .'">';
			echo '</a>';
			echo '</div>';
			echo '</div>';
		}
	?>

    <?php
		$title_exp = Helper::getField('title_exp','option');
		$list_exp = Helper::getField('list_exp','option');
		if($list_exp){
			echo '<div class="section-route-experience section-padding">';
			echo '<div class="container">';
			if($title_exp){
				echo '<h2 class="heading-title text-center">'. $title_exp .'</h2>';
			}
			echo '<ul class="wrapper">';
			foreach($list_exp as $val){
				echo '<li class="item flex">';
				echo '<div class="cover">';
				echo '<span class="scale res ar-1-1">';
				echo '<img src="'. $val['img'] .'">';
				echo '</span>';
				echo '</div>';
				echo '<div class="content text-center flex align-center">';
				echo '<h3 class="title">'. $val['title'] .'</h3>';
				echo '<div class="desc">'. $val['content'] .'</div>';
				if($val['btn_link']){
					echo '<a href="'. $val['btn_link'] .'" class="btn-main">Tìm hiểu thêm</a>';
				}
				echo '</div>';
				echo '</li>';
			}
			echo '</ul>';
			echo '</div>';
			echo '</div>';
		}
	?>

    <?php
		$title_tips = get_field('title_tips');
		$list_tips = get_field('list_tips');
		if($list_tips){
			echo '<div class="section-route-experience route-tips section-padding">';
			echo '<div class="container">';
			if($title_tips){
				echo '<h2 class="heading-title text-center">'. $title_tips .'</h2>';
			}
			echo '<ul class="wrapper">';
			$i = 0;
			foreach($list_tips as $val){
				$i++ ?>
    <li class="item flex">
        <div class="cover relative">
            <?php if (!empty($val['gallery'])): ?>
            <span class="block scale res ar-1-1 bg-icon">
                <img src="<?php echo $val['img']; ?>" alt="img">
            </span>
            <?php foreach ($val['gallery'] as $index => $gallery): ?>
            <?php if ($index === 0): ?>
            <a href="<?php echo $gallery; ?>" class="block relative thumb-first"
                data-fancybox="gallery-<?php echo $i; ?>">
                <span class="scale res ar-1-1">
                    <img src="<?php echo $gallery; ?>" alt="">
                </span>
            </a>
            <?php else: ?>
            <a href="<?php echo $gallery; ?>" data-fancybox="gallery-<?php echo $i; ?>" style="display:none;">
                <img src="<?php echo $gallery; ?>" alt="">
            </a>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php else: ?>
            <span class="scale res ar-1-1">
                <img src="<?php echo $val['img']; ?>" alt="img">
            </span>
            <?php endif; ?>
        </div>


        <!-- <div class="cover">
						<span class="scale res ar-1-1">
							<img src="<?php echo $val['img']; ?>">
						</span>
						
						<?php if($val['gallery']){
							echo '<div class="cover-gallery">';
							foreach($val['gallery'] as $gallery){ ?>
								<a href="<?php echo $gallery ?>" data-fancybox="gallery-<?php echo $i; ?>">
									<img src="<?php echo $gallery; ?>" alt="">
								</a>
							<?php } ?>
							</div>
						<?php } ?>
					</div> -->
        <div class="content text-center flex align-center">
            <h3 class="title">
                <?php if($val['link']){ echo '<a href="'. $val['link'] .'">'; } ?>
                <?php echo $val['title'] ?>
                <?php if($val['link']){ echo '</a>'; } ?>
            </h3>
        </div>
    </li>
    <?php }
			echo '</ul>';
			echo '</div>';
			echo '</div>';
		}
	?>
    <?php echo do_shortcode('[section_partner]'); ?>
    <?php get_template_part('template-blocks/section-newspapers'); ?>
    <?php //get_template_part('template-blocks/section-posts'); ?>

    <?php $title_posts = get_field('title_posts');
	$sl_posts = get_field('sl_posts');
	if($sl_posts){ ?>
    <section class="section home-posts section-padding">
        <div class="container">
            <?php if($title_posts){
				echo '<h2 class="heading-title text-center">'. $title_posts .'</h2>';
			} ?>
            <div class="home-posts__content">
                <?php if ( $sl_posts ) : ?>
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
                            <?php foreach ( $sl_posts as $post ) :
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
                                            <a class="link-cover" href="<?= get_permalink( $post->ID ) ?>"
                                                aria-label="<?= Helper::escAttr( $post_title ) ?>"></a>
                                        </span>
                                    </div>
                                    <div class="content">
                                        <h3 class="title">
                                            <a href="<?= get_permalink( $post->ID ) ?>"
                                                aria-label="<?= Helper::escAttr( $post_title ) ?>"><?= $post_title ?></a>
                                        </h3>
                                        <?= Helper::loopExcerpt( $post ) ?>
                                        <a href="<?= get_permalink( $post->ID ) ?>" class="btn-see-more">Đọc tiếp <i
                                                class="fa-regular fa-arrow-right-long"></i></a>
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

</div>
<?php

// footer
get_footer( );