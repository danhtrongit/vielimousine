<?php
/**
 * The Template for displaying all pages.
 * http://codex.wordpress.org/Template_Hierarchy
 *
 * @author Gaudev
 */

\defined( 'ABSPATH' ) || die;

// header
get_header( 'page' );

if ( have_posts() ) {
	the_post();
}

if ( post_password_required() ) {
	echo get_the_password_form();
	get_footer( 'page' );

	return;
}

/**
 * HOOK: hd_page_before_action
 */
do_action( 'hd_page_before_action' );

$alternative_title = \HD\Helper::getField( 'alternative_title', $post->ID );
$html_field = get_field('html_field');
if($html_field){ ?>
	<section class="section page-booking-success">
	<div class="section-custom-html section-padding">
		<div class="container">
			<?php if($html_field){
				echo '<div class="code">'. $html_field .'</div>';
			} ?>
		</div>
	</div>
</section>
<?php } else {
	// breadcrumbs
	\HD\Helper::blockTemplate( 'template-blocks/breadcrumbs', [
			'title' => get_the_title( $post->ID )
		]
	); ?>
	<section class="section section-page singular">
		<div class="container">
			<h1 class="heading-title" <?= \HD\Helper::microdata( 'headline' ) ?>><?= $alternative_title ?: get_the_title() ?></h1>
			<div class="content">
				<?php echo \HD\Helper::postExcerpt( $post, 'excerpt', false ); ?>
				<article <?= \HD\Helper::microdata( 'article' ) ?>>
					<?php
					the_content();
					\HD\Helper::blockTemplate( 'template-blocks/suggestion-posts' );
					?>
				</article>
			</div>
			<?php if ( is_active_sidebar( 'page-sidebar' ) ) : ?>
			<aside class="sidebar" <?= \HD\Helper::microdata( 'sidebar' ) ?>>
				<?php dynamic_sidebar( 'page-sidebar' ); ?>
			</aside>
			<?php endif;

			/**
			 * HOOK: hd_singular_sidebar_action
			 */
			do_action( 'hd_singular_sidebar_action' );
			?>
		</div>
	</section>
<?php } ?>


<?php

/**
 * HOOK: hd_page_after_action
 */
do_action( 'hd_page_after_action' );

// footer
get_footer( 'page' );
