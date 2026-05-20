<?php
use HD\Helper;
\defined( 'ABSPATH' ) || die;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_promotion' ) {
	return;
}
$col_video = $args['col_video'] ?? '';
$col_promo = $args['col_promo'] ?? false;
  ?>
<section class="section car-rental__promotion section-padding">
    <div class="container">
        <div class="wrapper flex flex-x">
            <div class="cell col-video relative">
                <?php $link_video = $col_video['link_video'];
                $tag = $col_video['tag'];
                $content = $col_video['content'] ?>
                <?php 
                parse_str( parse_url( $link_video, PHP_URL_QUERY ), $video_id );
                $video_id = $video_id['v']; ?>
                <div class="video-btn" data-video="<?php echo $video_id; ?>">
                    <img class="full_relative" src='https://i.ytimg.com/vi/<?php echo $video_id; ?>/maxresdefault.jpg' alt="video thumbnail">
                </div>
                <a href="<?php echo $link_video; ?>" class="ic-play block fcy-video"><i class="fas fa-play"></i></a>
                <div class="content">
                    <?php if($tag){ echo '<span class="tag">'. $tag .'</span>'; }
                    if($content){
                        echo '<div class="desc">'. $content .'</div>';
                    } ?>
                </div>
            </div>
            <div class="cell col-promotion relative">
                <?php $title = $col_promo['title'];
                $end_time = $col_promo['time_out'];
                $link = $col_promo['link_booking'];
                $bg = $col_promo['bg'];
                if($bg){ ?>
                    <style>
                        .col-promotion {
                            background-image: url('<?php echo $bg; ?>');
                            background-repeat: no-repeat;
                            background-size: cover;
                        }
                    </style>
                <?php } ?>
                <?php if ($end_time): ?>
                    <div class="inner relative">
                        <?php if($title){
                            echo '<h2 class="heading-title text-center">'. $title .'</h2>';
                        } ?>
                        <div id="countdown-wrapper">
                            <div id="countdown" data-endtime="<?= esc_attr($end_time); ?>">
                                <div class="countdown-item"><span id="days">00</span><div>Ngày</div></div>
                                <div class="countdown-item"><span id="hours">00</span><div>Giờ</div></div>
                                <div class="countdown-item"><span id="minutes">00</span><div>Phút</div></div>
                                <div class="countdown-item"><span id="seconds">00</span><div>Giây</div></div>
                            </div>
                        </div>
                        <?php if($link){ ?>
                            <a href="<?php echo $link; ?>" class="btn-main">Đặt vé ngay</a>
                        <?php } ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>