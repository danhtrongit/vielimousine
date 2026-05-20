<?php
/**
 * The template for displaying archive.
 *
 * @author Gaudev
 */
use HD\Helper;
\defined( 'ABSPATH' ) || die;

// header
get_header( 'archive' );

$object = get_queried_object();

// breadcrumbs
// \HD\Helper::blockTemplate( 'template-blocks/breadcrumbs', [
// 		'title' => get_the_archive_title()
// 	]
// );

/**
 * HOOK: hd_archive_before_action
 */
do_action( 'hd_archive_before_action' );
$css_class = get_field('css_class', 'category_' . $object->term_id);
$term = get_queried_object();

?>
<section class="section section-page archive archive-post <?php echo $css_class; ?>">
    <div class="container">
		<h1 class="title-cat text-center"><?php echo $term->name; ?></h1>
        <div class="content">
	        <?php if ( have_posts() ) : $counter = 0; ?>
            <div class="posts-list archive-list items-list flex flex-x">
            <?php while ( have_posts() ) : the_post(); ?>
                <div class="cell">
                    <?php get_template_part( 'template-parts/posts/loop', null, [ 'title_tag' => 'h2' ] ); ?>
                </div>
                <?php endwhile; ?>
            </div>
            <?php
		        // Previous/next page navigation.
		        \HD\Helper::paginateLinks();
	        else :
		        \HD\Helper::blockTemplate( 'template-blocks/no-results' );
	        endif;
	        ?>
	        <?= \HD\Helper::termExcerpt( $object->term_id, 'excerpt', 'div' ) ?>
        </div>
	    <?php if ( is_active_sidebar( 'archive-sidebar' ) ) : ?>
        <aside class="sidebar" <?= \HD\Helper::microdata( 'sidebar' ) ?>>
            <?php dynamic_sidebar( 'archive-sidebar' ); ?>
        </aside>
	    <?php endif;

	    /**
	     * HOOK: hd_archive_sidebar_action
	     */
	    do_action( 'hd_archive_sidebar_action' );
	    ?>
	</div>
</section>
<?php

/**
 * HOOK: hd_archive_after_action
 */
do_action( 'hd_archive_after_action' );

// footer
get_footer( 'archive' );
