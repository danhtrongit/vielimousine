<?php
declare(strict_types=1);

namespace Vie\Support;

final class MediaTransformer
{
    /**
     * Chuyển WP_Post (post_type=attachment) thành mảng API shape.
     */
    public static function one(\WP_Post $att): array
    {
        $meta  = wp_get_attachment_metadata($att->ID) ?: [];
        $sizes = [];

        foreach (['thumbnail', 'medium', 'large', 'full'] as $size) {
            $src = wp_get_attachment_image_src($att->ID, $size);
            if ($src) {
                $sizes[$size] = [
                    'url'    => (string) $src[0],
                    'width'  => (int) $src[1],
                    'height' => (int) $src[2],
                ];
            }
        }

        return [
            'id'         => (int) $att->ID,
            'title'      => (string) $att->post_title,
            'alt'        => (string) get_post_meta($att->ID, '_wp_attachment_image_alt', true),
            'caption'    => (string) $att->post_excerpt,
            'mime'       => (string) $att->post_mime_type,
            'filesize'   => isset($meta['filesize']) ? (int) $meta['filesize'] : 0,
            'width'      => isset($meta['width']) ? (int) $meta['width'] : 0,
            'height'     => isset($meta['height']) ? (int) $meta['height'] : 0,
            'url'        => (string) wp_get_attachment_url($att->ID),
            'sizes'      => $sizes,
            'created_at' => (string) $att->post_date_gmt,
        ];
    }
}
