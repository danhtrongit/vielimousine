<?php
/**
 * The Template for displaying product archives, including the main shop page which is a post type archive
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/archive-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.6.0
 */

 use HD\Helper;

\defined( 'ABSPATH' ) || die;

// header
get_header( 'shop' );

// template-parts/parts/archive-title.php
//the_archive_title_theme();

/**
 * Hook: woocommerce_before_main_content.
 *
 * @see woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
 * @see woocommerce_breadcrumb - 20 (removed by Theme)
 * @see WC_Structured_Data::generate_website_data() - 30
 */
do_action( 'woocommerce_before_main_content' );
$title_product = get_field('title_product','option');
$desc_product = get_field('desc_product','option');
?>
<section class="section archive archive-product">
    <?php
    // breadcrumbs
    \HD\Helper::blockTemplate( 'template-blocks/breadcrumbs', [
        'title' => \HD\Helper::primaryTerm( $post )?->name
    ]
    );
    ?>
	<div class="container">
		<?php if($title_product) {
			echo '<div class="heading-group text-center">';
			echo '<p class="heading-title">'. $title_product .'</p>';
            if($desc_product){
                echo '<div class="desc">'. $desc_product .'</div>';
            }
			echo '</div>';
		} ?>
		<div class="grid-products">
			<div class="content-col">
			<?php 
			$product_categories = get_terms( [
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			] );
			if ( ! empty( $product_categories ) && ! is_wp_error( $product_categories ) ) :
				foreach ( $product_categories as $cat ) : ?>
					<section class="category-block">
						<div class="heading-custom relative">
							<h2 class="category-title">
								<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>">
									<?php echo esc_html( $cat->name ); ?>
								</a>
							</h2>
						</div>
						<ul class="products columns-3">
							<?php
							$product_args = [
								'post_type'      => 'product',
								'posts_per_page' => 6,
								'tax_query'      => [
									[
										'taxonomy' => 'product_cat',
										'field'    => 'term_id',
										'terms'    => $cat->term_id,
									],
								],
							];
							$product_query = new WP_Query( $product_args );
							if ( $product_query->have_posts() ) :
								while ( $product_query->have_posts() ) : $product_query->the_post(); ?>
									<li class="product">
										<div class="item">
											<a href="<?php the_permalink(); ?>" class="woocommerce-LoopProduct-link woocommerce-loop-product__link">
												<div class="overlay relative">
													<?php woocommerce_template_loop_product_thumbnail(); ?>
												</div>	
												<h3 class="woocommerce-loop-product__title"><?php the_title(); ?></h3>
											</a>
											<?php woocommerce_template_loop_price(); ?>
											<a href="<?php the_permalink(); ?>" class="btn-product">
												<span class="text">Xem sản phẩm</span>
											</a>
										</div>
									</li>
								<?php endwhile;
								wp_reset_postdata();
							else :
								echo '<p>Không có sản phẩm.</p>';
							endif; ?>
						</ul>
					</section>
					<?php
				endforeach;
			endif;
			?>
			</div>
		</div>
		
		<?php
		/**
		 * Hook: woocommerce_archive_description.
		 *
		 * @since 1.6.2.
		 * @see woocommerce_taxonomy_archive_description - 10
		 * @see woocommerce_product_archive_description - 10
		 */
		do_action( 'woocommerce_archive_description' ); ?>
	</div>
</section>
<?php

/**
 * Hook: woocommerce_after_main_content.
 *
 * @see woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
 */
do_action( 'woocommerce_after_main_content' );

// footer
get_footer( 'page' );
