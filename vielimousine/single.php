<?php
/**
 * The Template for displaying all single posts.
 * http://codex.wordpress.org/Template_Hierarchy
 *
 * @author Vie
 */

\defined( 'ABSPATH' ) || die;

// header
get_header( 'single' );

if ( have_posts() ) {
	the_post();
}

if ( post_password_required() ) {
	echo get_the_password_form();
	get_footer( 'single' );

	return;
}

/**
 * HOOK: hd_single_before_action
 */
do_action( 'hd_single_before_action' );

$alternative_title = \HD\Helper::getField( 'alternative_title', $post->ID );
?>
<section class="section section-page section-single singular">
    <div class="container flex flex-x">
        <div class="content-post">
            <p class="heading-title" <?= \HD\Helper::microdata( 'headline' ) ?>><?= $alternative_title ?: get_the_title() ?></p>
            <div class="meta">
                <?php
                    $categories = get_the_category();
                    if ( ! empty( $categories ) ) {
                        $output = [];
                        foreach ( $categories as $category ) {
                            $output[] = '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '" class="post-category">' . esc_html( $category->name ) . '</a>';
                        }
                        echo implode( ', ', $output );
                    }
                ?>
                <div class="author">
                    / Bởi <?php echo get_the_author() ?>
                </div>
            </div>
            <article <?= \HD\Helper::microdata( 'article' ) ?>>
                <?php the_content(); ?>
            </article>
            <?php \HD\Helper::hashTags(); ?>
            
            <div class="author-box">
                <div class="inner flex align-center">
                    <div class="avatar">
                        <?php echo get_avatar( get_the_author_meta( 'ID' ), 80 ); ?>
                    </div>
                    <div class="author-content">
                        <div class="name">
                        <a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>" target="_blank" class="author-link">
                            <strong>TÁC GIẢ</strong><span><?php the_author_meta( 'display_name' ); ?></span>
                        </a>
                        </div>
                        <?php $authorDesc = get_field('shortdesc_author', 'user_' . get_the_author_meta( 'ID' ));
                        if($authorDesc){ ?>
                            <div class="author-desc"><?php echo $authorDesc; ?></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            

            <?php \HD\Helper::blockTemplate( 'template-blocks/related-posts', [
                'title'    => __( 'Bài Viết Liên Quan', TEXT_DOMAIN ),
                'taxonomy' => 'category',
                'post_id'  => $post->ID,
                'max'      => 12
                ]
            ); ?>
        </div>
        
        <?php
        /**
         * HOOK: hd_singular_sidebar_action
         */
        do_action( 'hd_singular_sidebar_action' );

        ?>
    </div>
</section>
<?php

/**
 * HOOK: hd_single_after_action
 */
do_action( 'hd_single_after_action' );

// footer
get_footer( 'single' );
