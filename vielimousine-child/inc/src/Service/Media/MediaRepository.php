<?php
declare(strict_types=1);

namespace Vie\Service\Media;

final class MediaRepository
{
    /**
     * @return array{data: \WP_Post[], pagination: array{page:int,per_page:int,total:int,total_pages:int,has_next:bool,has_prev:bool}}
     */
    public function paginate(array $params): array
    {
        $per  = max(1, min(60, (int) ($params['per_page'] ?? 24)));
        $page = max(1, (int) ($params['page'] ?? 1));

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => $per,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if (!empty($params['q'])) {
            $args['s'] = (string) $params['q'];
        }

        $q          = new \WP_Query($args);
        $total      = (int) $q->found_posts;
        $totalPages = (int) $q->max_num_pages;

        return [
            'data'       => $q->posts,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per,
                'total'       => $total,
                'total_pages' => $totalPages,
                'has_next'    => $page < $totalPages,
                'has_prev'    => $page > 1,
            ],
        ];
    }
}
