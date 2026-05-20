<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'home_popular_routes' ) {
	return;
}
$heading_title = $args['heading_title'] ?? '';
$lists_routes = $args['lists_routes'] ?? '';  ?>
<section class="home-popular section-padding relative">
    <div class="container">
        <h2 class="heading-title text-center"><?php echo $heading_title; ?></h2>
        <?php if($lists_routes) {
            echo '<ul class="wrapper">';
            foreach($lists_routes as $val){ ?>
                <li class="item relative">
                    <div class="box">
                        <a href="<?php echo $val['link'] ?>" class="cover relative">
                            <div class="img relative">
                                <img src="<?php echo $val['img'] ?>" class="absolute" alt="image">
                            </div>
                            <h3 class="title"><?php echo $val['title']; ?></h3>
                        </a>
                        <div class="content">
                            <div class="rating flex flex-x align-center">
                                <div class="score">
                                    <span class="tag"><?php echo $val['rating']['score'] ?></span>
                                    <span class="text"><?php echo $val['rating']['note'] ?></span>
                                </div>
                                <?php if($val['rating']['num_rating']){ ?>
                                    <div class="number"><?php echo $val['rating']['num_rating'] ?> đánh giá</div>
                                <?php } ?>
                            </div>
                            <?php if($val['utilities']) { ?>
                            <div class="utilities-lists flex flex-x">
                                <?php foreach($val['utilities'] as $item){
                                    echo '<p class="text">'. $item['content'] .'</p>';
                                } ?>
                            </div>
                            <?php } ?>
                            <div class="bottom flex flex-x">
                            <?php if($val['ticket_price']) { ?>
                                <div class="main-price">
                                    <span class="sub">Giá vé</span>
                                    <span class="price"><?php echo $val['ticket_price'] ?></span>
                                </div>
                            <?php } ?>
                            <a href="<?php if($val['link']) { echo $val['link']; } else { echo 'javascript:void(0)'; } ?>" class="btn-main flex">Xem chi tiết</a>
                            </div>
                        </div>
                        <span class="tag-popular absolute">Tuyến xe yêu thích</span>
                    </div>
                </li>
            <?php }
            echo '</ul>';
        } ?>
    </div>
</section>