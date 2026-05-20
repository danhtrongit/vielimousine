<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_route' ) {
	return;
}
$heading_title = $args['title'] ?? '';
$lists_routes = $args['lists_route'] ?? '';  ?>
<section class="section hotel-route home-popular relative">
    <div class="container">
        <h2 class="heading-title text-center"><?php echo $heading_title; ?></h2>
        <?php if($lists_routes) {
            echo '<ul class="wrapper">';
            foreach($lists_routes as $val){ ?>
                <li class="item relative">
                    <a href="<?php echo $val['link'] ?>" class="cover relative">
                        <div class="img relative">
                            <img src="<?php echo $val['img'] ?>" class="absolute" alt="image">
                        </div>
                        <h3 class="title"><?php echo $val['title']; ?></h3>
                    </a>
                    <!-- <span class="tag-popular absolute">Tuyến xe yêu thích</span> -->
                </li>
            <?php }
            echo '</ul>';
        } ?>
    </div>
</section>