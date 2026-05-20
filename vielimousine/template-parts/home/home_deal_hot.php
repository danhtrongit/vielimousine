<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'home_deal_hot' ) {
	return;
}
$heading_title = $args['heading_title'] ?? '';
$lists_deal = $args['lists_deal'] ?? '';
$btn_link = $args['btn_link'] ?? '';
$btn_name = $args['btn_name'] ?? 'Xem thêm'; ?>
<section class="home-deal section-padding">
    <div class="container">
        <?php if($heading_title){
            echo '<h2 class="heading-title text-center">'. $heading_title .'</h2>';
        } ?>
        <?php if($lists_deal) {
            echo '<ul class="wrapper gap-20">';
            foreach($lists_deal as $val){
                $price = $val['price']; ?>
                <li class="item relative flex flex-column">
                    <a href="<?php echo $val['link'] ?>" class="cover relative">
                        <img src="<?php echo $val['img'] ?>" class="absolute" alt="image">
                        <span class="bage">Hot Deal</span>
                    </a>
                    <div class="content flex flex-column">
                        <h3 class="title">
                            <a href="<?php echo $val['link'] ?>"><?php echo $val['title']; ?></a>
                        </h3>
                        <?php if($val['combo_text']){
                            echo '<div class="combo-text">'. $val['combo_text'] .'</div>';
                        } ?>
                        <!-- <div class="rating flex flex-x align-center justify-space-between">
                            <?php //if($val['rating']) { echo '<span class="text">'. $val['rating'] .'</span>'; } ?>
                            <div class="star">
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                            </div>
                        </div> -->
                        <?php if($val['address']) { echo '<div class="address">'. $val['address'] .'</div>'; } ?>
                        <?php if($price){
                            echo '<div class="group-price flex align-center justify-space-between">';
                            if($price['price_sale']){
                                echo '<div class="price-sale">'. $price['price_sale'] .'</div>';
                            }
                            if($price['price_main']){
                                echo '<div class="price-main">'. $price['price_main'] .'</div>';
                            }
                            echo '</div>';
                        }?>
                    </div>
                    <!-- <span class="tag-popular absolute">Tuyến xe yêu thích</span> -->
                </li>
            <?php }
            echo '</ul>';
        }
        if($btn_link){
            echo '<a href="'. $btn_link .'" class="btn-main ml-auto mr-auto">'. $btn_name .'</a>';
        } ?>
    </div>
</section>