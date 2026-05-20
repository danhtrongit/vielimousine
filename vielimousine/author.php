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

/**
 * HOOK: hd_archive_before_action
 */
do_action( 'hd_archive_before_action' );
$term = get_queried_object();
?>
<section class="section section-page archive archive-post">
    <div class="container">
		<p class="title-cat text-center"><?php echo $term->display_name; ?></p>
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
        </div>
	</div>
</section>
<?php

/**
 * HOOK: hd_archive_after_action
 */
do_action( 'hd_archive_after_action' );

// footer
get_footer( 'archive' );