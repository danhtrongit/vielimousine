<?php
declare(strict_types=1);

namespace Vie\Service\Hotel;

use Vie\Repository\HotelRepository;

/**
 * Sync 2 chiều giữa bảng `vie_hotel` và WP post_type=hotel.
 *
 * Field mapping 2-way: post_title↔name, post_name↔slug, post_content↔description,
 * post_status↔is_active, postmeta `hotel_address`↔address, `_thumbnail_id`↔thumbnail_id.
 *
 * Loop prevention: static $syncing flag — set trước mọi write, reset trong finally.
 */
final class HotelSyncService
{
    private static bool $syncing = false;

    public function __construct(
        private readonly HotelRepository $hotelRepo,
    ) {
    }

    /**
     * Tạo WP post mới khi SPA POST /hotels không có post_id.
     */
    public function createPost(string $name, ?string $slug, ?string $description): int
    {
        self::$syncing = true;
        try {
            $postId = wp_insert_post([
                'post_type'    => 'hotel',
                'post_status'  => 'publish',
                'post_title'   => $name,
                'post_name'    => $slug !== null && $slug !== '' ? $slug : sanitize_title($name),
                'post_content' => $description ?? '',
            ], true);

            if (is_wp_error($postId)) {
                throw new \RuntimeException('Không tạo được WP post: ' . $postId->get_error_message());
            }
            return (int) $postId;
        } finally {
            self::$syncing = false;
        }
    }

    /**
     * vie_hotel → WP post (gọi sau khi SPA create/update hotel).
     *
     * @param array<string,mixed> $hotel  Row vie_hotel đã có post_id.
     */
    public function pushToPost(array $hotel): void
    {
        if (self::$syncing) {
            return;
        }
        $postId = (int) ($hotel['post_id'] ?? 0);
        if ($postId <= 0) {
            return;
        }
        if (get_post($postId) === null) {
            return;
        }

        self::$syncing = true;
        try {
            $isActive  = isset($hotel['is_active']) ? (bool) $hotel['is_active'] : true;
            $postData = [
                'ID'           => $postId,
                'post_title'   => (string) ($hotel['name'] ?? ''),
                'post_content' => (string) ($hotel['description'] ?? ''),
                'post_status'  => $isActive ? 'publish' : 'draft',
            ];
            if (!empty($hotel['slug'])) {
                $postData['post_name'] = (string) $hotel['slug'];
            }
            wp_update_post($postData);

            if (array_key_exists('address', $hotel)) {
                update_post_meta($postId, 'hotel_address', (string) ($hotel['address'] ?? ''));
            }
            if (!empty($hotel['thumbnail_id'])) {
                update_post_meta($postId, '_thumbnail_id', (int) $hotel['thumbnail_id']);
            }
        } finally {
            self::$syncing = false;
        }
    }

    /**
     * WP post → vie_hotel (gọi từ save_post_hotel hook).
     */
    public function pullFromPost(int $postId, \WP_Post $post): void
    {
        if (self::$syncing) {
            return;
        }
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        if ($post->post_type !== 'hotel') {
            return;
        }
        if ($post->post_status === 'auto-draft' || $post->post_status === 'trash') {
            return;
        }

        self::$syncing = true;
        try {
            $existing = $this->hotelRepo->findByPostId($postId);

            $data = [
                'post_id'      => $postId,
                'name'         => (string) $post->post_title,
                'slug'         => (string) $post->post_name,
                'description'  => (string) $post->post_content,
                'is_active'    => $post->post_status === 'publish' ? 1 : 0,
                'address'      => (string) get_post_meta($postId, 'hotel_address', true),
            ];

            $thumb = (int) get_post_meta($postId, '_thumbnail_id', true);
            if ($thumb > 0) {
                $data['thumbnail_id'] = $thumb;
            }

            if ($existing === null) {
                $this->hotelRepo->create($data);
            } else {
                $this->hotelRepo->update((int) $existing['id'], $data);
            }
        } finally {
            self::$syncing = false;
        }
    }

    /**
     * Scan mọi post_type=hotel, đồng bộ vào vie_hotel.
     * Dùng cho first-run + manual sync button.
     *
     * @return array{created:int, updated:int, skipped:int}
     */
    public function backfillAll(): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $posts = get_posts([
            'post_type'     => 'hotel',
            'post_status'   => ['publish', 'draft', 'private'],
            'numberposts'   => -1,
            'no_found_rows' => true,
            'orderby'       => 'ID',
            'order'         => 'ASC',
        ]);

        foreach ($posts as $post) {
            if (!($post instanceof \WP_Post)) {
                continue;
            }
            $existing  = $this->hotelRepo->findByPostId((int) $post->ID);
            $beforeKey = $existing !== null ? $this->fingerprint($existing) : null;

            $this->pullFromPost((int) $post->ID, $post);

            $after    = $this->hotelRepo->findByPostId((int) $post->ID);
            $afterKey = $after !== null ? $this->fingerprint($after) : null;

            if ($existing === null && $after !== null) {
                $stats['created']++;
            } elseif ($beforeKey !== $afterKey) {
                $stats['updated']++;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    private function fingerprint(array $hotel): string
    {
        return md5(implode('|', [
            (string) ($hotel['name'] ?? ''),
            (string) ($hotel['slug'] ?? ''),
            (string) ($hotel['description'] ?? ''),
            (string) ($hotel['address'] ?? ''),
            (int)    ($hotel['is_active'] ?? 0),
            (int)    ($hotel['thumbnail_id'] ?? 0),
        ]));
    }
}
