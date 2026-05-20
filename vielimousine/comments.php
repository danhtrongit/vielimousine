<?php

/**
 * The template for displaying comments
 *
 * This is the template that displays the area of the page that contains both the current comments
 * and the comment form.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 */

if ( post_password_required() ) {
    return;
}

$comment_count = get_comments_number();
?>
<div class="wp-comments-area comments-area">
    <div class="comments-title h5"><?php echo __( 'Bình luận', TEXT_DOMAIN ) ?> <span>(<?php echo $comment_count ?>)</span></div>
	<?php

		comment_form(
			array(
				'logged_in_as'       => null,
				'title_reply'        => esc_html__( 'Để lại một bình luận', TEXT_DOMAIN ),
				'title_reply_before' => '<div id="reply-title" class="comment-reply-title">',
				'title_reply_after'  => '</div>',
				'comment_field'      => '<p class="comment-form-comment"><label class="_label_title" for="comment">' . esc_html__( 'Nội dung', TEXT_DOMAIN ) . '</label><textarea id="comment" name="comment" cols="45" rows="5" placeholder="Để lại bình luận của bạn tại đây nhé..."></textarea></p>',
				'comment_notes_before' => '<p class="comment-notes">' . esc_html__( 'Chúng tôi không công khai email của bạn. Các trường bắt buộc được đánh dấu *', TEXT_DOMAIN ) . '</p>',
				'fields'             => array(
					'author' => '<p class="comment-form-author hidden"><label class="_label_title"  for="author">' . esc_html__( 'Tên của bạn (*)', TEXT_DOMAIN ) . '</label><input id="author" name="author" placeholder="Tên của bạn...." type="text" required /></p>',
					'email'  => '<p class="comment-form-email hidden"><label class="_label_title"  for="email">' . esc_html__( 'Email (*)', TEXT_DOMAIN ) . '</label><input id="email" name="email" placeholder="Email của bạn...." type="email" required /></p>',
					'cookies' => '' // Ẩn ô "Lưu thông tin"
				),
				'submit_button'      => '<button type="submit" class="submit comment-form-button hidden">' . esc_html__( 'Gửi bình luận', TEXT_DOMAIN ) . '</button>',
			)
		);

    ?>
    <?php if ( have_comments() ) : ?>
    <span class="fs-16 medium block">
        <?php if ( '1' === $comment_count ) : ?>
            <?php esc_html_e( '1 bình luận', TEXT_DOMAIN ); ?>
        <?php else : ?>
            <?php
            printf(
            /* translators: %s: Comment count number. */
                esc_html( _nx( '%s bình luận', '%s bình luận', $comment_count, 'Comments title', TEXT_DOMAIN ) ),
                esc_html( number_format_i18n( $comment_count ) )
            );
            ?>
        <?php endif; ?>
    </span><!-- .comments-title -->
	
	<ol class="comment-list">
		<?php
		$comments = get_comments(array('post_id' => get_the_ID()));
		foreach ($comments as $comment) :
		?>
			<li class="comment">
				<div class="comment-body">
					<div class="comment-meta">
						<div class="comment-avatar">
							<?php echo get_avatar($comment, 50); // Hiển thị avatar với kích thước 50px ?>
						</div>
						<div class="comment-info">
							<span class="comment-author"><?php echo get_comment_author($comment); ?></span>
							<span class="comment-date"><?php echo get_comment_date('d/m/Y - H:i', $comment); ?></span>
						</div>
					</div>
					<div class="comment-content">
						<?php echo get_comment_text($comment); ?>
					</div>
					<?php if ($comment->comment_approved == '0') : ?>
						<p class="comment-awaiting-moderation">
							<?php esc_html_e('Bình luận của bạn đang chờ xét duyệt. Đây là bản xem trước; bình luận của bạn sẽ hiển thị sau khi được chấp nhận.', 'hd'); ?>
						</p>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ol>


    <?php
    the_comments_pagination(
        [
            'before_page_number' => esc_html__( 'Trang', TEXT_DOMAIN ) . ' ',
            'mid_size'           => 0,
            'prev_text'          => sprintf(
                '%s <span class="nav-prev-text">%s</span>',
                is_rtl() ? '<i class="fas fa-arrow-right"></i>' : '<i class="fas fa-arrow-left"></i>',
                esc_html__( 'Nhận xét cũ hơn', TEXT_DOMAIN )
            ),
            'next_text'          => sprintf(
                '<span class="nav-next-text">%s</span> %s',
                esc_html__( 'Bình luận mới hơn', TEXT_DOMAIN ),
                is_rtl() ? '<i class="fas fa-arrow-right"></i>' : '<i class="fas fa-arrow-left"></i>'
            ),
        ]
    );
    ?>
    <!-- <'?php if ( ! comments_open() ) : ?>
    	<p class="no-comments"><'?php esc_html_e( 'Bình luận đã đóng.', TEXT_DOMAIN ); ?></p>
    <'?php endif; ?>
    <'?php endif; ?> -->
	<?php else : ?>
		<div class="_noo">
			<p class="no-icons"><i class="fa-regular fa-flag"></i></p>
			<p class="no-comments"><?php esc_html_e( 'Hiện chưa có bình luận nào, hãy trở thành người đầu tiên bình luận cho bài viết này!', 'hd' ); ?></p>
		</div>
    <?php endif; ?>
</div>