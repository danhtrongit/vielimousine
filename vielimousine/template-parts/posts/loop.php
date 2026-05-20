<?php
/**
 * The loop.php file in WordPress handles displaying post's summaries in lists,
 * such as archives or blog pages v.v...
 *
 * @author Gaudev
 */

\defined( 'ABSPATH' ) || die;

global $post;

$title     = $args['title'] ?? get_the_title( $post->ID );
$ratio     = $args['ratio'] ?? \HD\Helper::aspectRatioClass( get_post_type( $post->ID ) );
$thumbnail = $args['large'] ?? \HD\Helper::postImageHTML( $post->ID, 'medium', [ 'alt' => \HD\Helper::escAttr( $title ) ] );
$title_tag = $args['title_tag'] ?? 'p';
$from = get_the_time( 'U', $post );
?>
<div class="item relative">
    <span class="cover">
        <span class="scale res <?= $ratio ?>">
            <?php echo $thumbnail; ?>
            <a class="link-cover" href="<?= get_permalink( $post->ID ) ?>" aria-label="<?= \HD\Helper::escAttr( $title ) ?>"></a>
        </span>
    </span>
    <div class="content">
        <?php echo '<' . $title_tag . ' class="title"><a href="' . get_permalink( $post->ID ) . '" title="' . \HD\Helper::escAttr( $title ) . '">' . $title . '</a></' . $title_tag . '>'; ?>
	    <div class="meta">
            <?php echo '<div class="post-date"><span class="date">'. date('j/m/Y', $from) .'</span></div>'; ?>
        </div>
        <?php echo \HD\Helper::loopExcerpt( $post ); ?>
        <a href="<?= get_permalink( $post->ID ) ?>" class="btn-see-more">Đọc tiếp <i class="fa-regular fa-arrow-right-long"></i></a>
    </div>
</div>
