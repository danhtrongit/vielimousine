<?php
declare(strict_types=1);

namespace Vie\Service\Media;

final class MediaService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_BYTES     = 10 * 1024 * 1024;

    public function upload(array $file): int
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new \RuntimeException('File rỗng hoặc không hợp lệ.');
        }
        if ($size > self::MAX_BYTES) {
            throw new \RuntimeException('File quá lớn (tối đa 10 MB).');
        }

        // Detect MIME bằng magic bytes thay vì trust $_FILES['type'] do client gửi
        // (có thể giả mạo). PHP_INT chỉ dùng client MIME như hint, không làm chuẩn.
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_readable($tmpPath)) {
            throw new \RuntimeException('Không thể đọc file upload.');
        }
        $detectedMime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detectedMime = (string) finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
            }
        }
        if ($detectedMime === '' || !in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            throw new \RuntimeException('Định dạng không cho phép (chỉ jpg/png/webp/gif).');
        }

        $upload = wp_handle_upload($file, [
            'test_form' => false,
            'mimes'     => [
                'jpg|jpeg' => 'image/jpeg',
                'png'      => 'image/png',
                'webp'     => 'image/webp',
                'gif'      => 'image/gif',
            ],
        ]);
        if (isset($upload['error'])) {
            throw new \RuntimeException((string) $upload['error']);
        }

        // Strip EXIF + re-encode (loại bỏ GPS, camera info, embedded scripts).
        // wp_get_image_editor giữ pixel data, drop metadata khi save.
        if (in_array($upload['type'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $editor = wp_get_image_editor($upload['file']);
            if (!is_wp_error($editor)) {
                $editor->save($upload['file'], $upload['type']);
            }
        }

        $attachmentId = wp_insert_attachment(
            [
                'guid'           => $upload['url'],
                'post_mime_type' => $upload['type'],
                'post_title'     => sanitize_file_name(pathinfo($file['name'] ?? 'image', PATHINFO_FILENAME)),
                'post_status'    => 'inherit',
            ],
            $upload['file']
        );
        if (is_wp_error($attachmentId)) {
            throw new \RuntimeException($attachmentId->get_error_message());
        }

        wp_update_attachment_metadata(
            (int) $attachmentId,
            wp_generate_attachment_metadata((int) $attachmentId, $upload['file'])
        );

        return (int) $attachmentId;
    }

    public function delete(int $id): void
    {
        $used = $this->isUsed($id);
        if (!empty($used)) {
            $names = array_map(static fn ($r) => sprintf('%s #%d (%s)', $r['type'], $r['id'], $r['name']), $used);
            throw new \RuntimeException('Ảnh đang được dùng trong: ' . implode(', ', $names));
        }
        $result = wp_delete_attachment($id, true);
        if ($result === false || $result === null) {
            throw new \RuntimeException('Không xóa được attachment.');
        }
    }

    public function updateMeta(int $id, array $data): void
    {
        $update = ['ID' => $id];
        if (array_key_exists('title', $data)) {
            $update['post_title'] = sanitize_text_field((string) $data['title']);
        }
        if (array_key_exists('caption', $data)) {
            $update['post_excerpt'] = sanitize_text_field((string) $data['caption']);
        }
        if (count($update) > 1) {
            wp_update_post($update);
        }
        if (array_key_exists('alt', $data)) {
            update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field((string) $data['alt']));
        }
    }

    /**
     * @return array<int, array{type:string, id:int, name:string}>
     */
    public function isUsed(int $id): array
    {
        if ($id <= 0) {
            return [];
        }
        global $wpdb;
        $hotel = $wpdb->prefix . 'vie_hotel';
        $room  = $wpdb->prefix . 'vie_room';
        $json  = wp_json_encode($id);

        $sql = $wpdb->prepare(
            "SELECT 'hotel' AS type, id, name FROM {$hotel}
              WHERE thumbnail_id = %d OR JSON_CONTAINS(COALESCE(gallery,'[]'), %s)
             UNION ALL
             SELECT 'room' AS type, id, name FROM {$room}
              WHERE thumbnail_id = %d OR JSON_CONTAINS(COALESCE(gallery,'[]'), %s)",
            $id,
            $json,
            $id,
            $json
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];

        return array_map(
            static fn ($r) => ['type' => (string) $r['type'], 'id' => (int) $r['id'], 'name' => (string) $r['name']],
            $rows
        );
    }
}
