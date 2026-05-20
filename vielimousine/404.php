<?php
/**
 * The template for displaying 404 pages (Not Found).
 * http://codex.wordpress.org/Template_Hierarchy
 *
 * @author Gaudev
 */

\defined( 'ABSPATH' ) || die;

// header
get_header( '404' );

// breadcrumbs
\HD\Helper::blockTemplate( 'template-blocks/breadcrumbs' );

?>
<section class="section section-page section-404 singular">
	<div class="container">
		<img src="<?php echo get_template_directory_uri() . '/resources/img/img-404.png'?>">
	</div>
</section>
<?php

// footer
get_footer( '404' );
