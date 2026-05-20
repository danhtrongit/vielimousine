<?php
\defined( 'ABSPATH' ) || die;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_content_gallery' ) {
	return;
}
$title = $args['title'] ?? '';
$content = $args['content'] ?? '';
$gallery = $args['gallery'] ?? ''; ?>
<section class="section about-content_gallery section-padding">
    <div class="container">
        <div class="wrapper flex flex-x">
            <div class="cell cell-content">
                <h2 class="heading-title">
                    <?php if($sub_title){
                        echo '<span class="sub-title">'. $sub_title .'</span>';
                    }
                    echo '<span>'. $title .'</span>'; ?>
                </h2>
                <?php if($content){
                    echo '<div class="content">'. $content .'</div>';
                } ?>
            </div>
            <div class="cell cell-gallery">
                <div class="gallery-wrapper gallery-pc">
                    <div class="gallery slider-for">
                        <?php if($gallery){
                            foreach($gallery as $image){ ?>
                                <div class="img">
                                    <a href="<?php echo esc_url($image['url']); ?>" class="relative block" data-fancybox="gallery-<?php echo $post->ID; ?>">
                                        <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" />
                                    </a>
                                </div>
                            <?php }
                        } ?>
                    </div>
                    <div class="gallery slider-nav">
                        <?php if($gallery){
                            if(wp_is_mobile()){
                                $max_thumbs = 3;
                            } else {
                                $max_thumbs = 4;
                            }
                            $total = count($gallery);
                            $has_extra = $total > $max_thumbs;
                            foreach( $gallery as $index => $image2 ) {
                                if ( $index < ($max_thumbs - 1) || !$has_extra ) { ?>
                                    <div class="img-dots">
                                        <div class="relative block img">
                                            <img src="<?php echo esc_url($image2['url']); ?>" alt="<?php echo esc_attr($image2['alt']); ?>" />
                                        </div>
                                    </div>
                                    <?php
                                } elseif ( $index === ($max_thumbs - 1) && $has_extra ) {
                                    $remaining = $total - $max_thumbs; ?>
                                    <div class="img-dots more">
                                        <div class="img">
                                            <a href="<?php echo esc_url($image2['url']); ?>" class="relative block" data-fancybox="gallery-<?php echo $post->ID; ?>">
                                                <img src="<?php echo esc_url($image2['url']); ?>" alt="<?php echo esc_attr($image2['alt']); ?>" />
                                                <span class="more-count">+<?php echo $remaining; ?></span>
                                            </a>
                                        </div>
                                    </div>
                                    <?php
                                    break;
                                } 
                            }
                        } ?>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</section>