<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_usp' ) {
	return;
}
$lists = $args['lists_box'] ?? '';  ?>
<section class="section home-usp relative">
    <div class="container">
        <div class="box section-padding">
            <?php if($lists) :
                echo '<ul class="wrapper">';
                foreach($lists as $val) :
                    echo '<li class="item">';
                    echo '<img src="'. $val['icon'] .'" alt="img">';
                    echo '<p class="title">'. $val['title'] .'</p>';
                    echo '</li>';
                endforeach;
                echo '</ul>';
            endif; ?>
        </div>
    </div>
</section>