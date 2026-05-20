<?php
use HD\Helper;
\defined( 'ABSPATH' ) || die;
$page_id = $args['page_id'] ?? null;
$title_faq = Helper::getField( 'title_faq_rental', $page_id );
$lists_faq = Helper::getField( 'lists_faq_rental', $page_id );
if($lists_faq){ ?>
    <section class="section section-faq section-padding relative">
        <div class="container">
            <?php if($title_faq){
                echo '<div class="heading-group text-center">';
                echo '<h2 class="heading-title">'. $title_faq .'</h2>';
                echo '<img src="'. get_template_directory_uri() . '/resources/img/line-title-vie.png' .'">';
                echo '</div>';
            } ?>
            <ul class="lists-faq">
                <?php foreach($lists_faq as $val){ ?>
                    <li class="toggle-item">
                        <div class="tab-title">
                            <p class="title"><?php echo $val['question']; ?></p>
                        </div>
                        <div class="tab-content">
                            <div class="content"><?php echo $val['answer']; ?></div>
                        </div>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </section>
<?php } ?>