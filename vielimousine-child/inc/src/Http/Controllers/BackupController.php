<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Service\Backup\BackupService;
use Vie\Support\ResponseEnvelope;

final class BackupController
{
    public static function tables(\WP_REST_Request $request): \WP_REST_Response
    {
        return ResponseEnvelope::success(BackupService::listTables());
    }

    public static function export(\WP_REST_Request $request): \WP_REST_Response
    {
        $body   = $request->get_json_params();
        $tables = is_array($body['tables'] ?? null) ? $body['tables'] : [];
        $tables = array_values(array_filter(array_map('strval', $tables), [BackupService::class, 'isAllowed']));
        if ($tables === []) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'tables', 'message' => 'Chọn ít nhất 1 bảng hợp lệ (vie_*)'],
            ], 422);
        }
        $sql = BackupService::export($tables);
        return ResponseEnvelope::success([
            'filename' => 'vie-backup-' . gmdate('Ymd-His') . '.sql',
            'sql'      => $sql,
            'tables'   => $tables,
            'bytes'    => strlen($sql),
        ]);
    }

    public static function restore(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params();
        if (($body['confirm'] ?? '') !== 'RESTORE') {
            return ResponseEnvelope::error([
                ['code' => 'confirm_required', 'field' => 'confirm', 'message' => 'Gõ chính xác RESTORE để xác nhận'],
            ], 422);
        }
        $sql = (string) ($body['sql'] ?? '');
        if (trim($sql) === '') {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'sql', 'message' => 'Thiếu nội dung SQL'],
            ], 422);
        }

        try {
            // auto-snapshot các bảng vie_* hiện có được nhắc tới trong file
            preg_match_all('/(?:DROP\s+TABLE\s+IF\s+EXISTS|CREATE\s+TABLE|INSERT\s+INTO)\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m);
            $refTables = array_values(array_filter(array_unique($m[1]), [BackupService::class, 'isAllowed']));

            $dir  = BackupService::backupDir(); // tạo thư mục + .htaccess deny-all + index.php
            $snap = $dir . '/auto-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.sql';
            $written = file_put_contents($snap, BackupService::export($refTables));
            if ($written === false) {
                return ResponseEnvelope::error([
                    ['code' => 'snapshot_failed', 'field' => null, 'message' => 'Không thể ghi auto-snapshot trước khi phục hồi — hủy restore'],
                ], 500);
            }

            $res = BackupService::restore($sql);
            $res['snapshot_file'] = str_replace(wp_upload_dir()['basedir'], '', $snap);
            return ResponseEnvelope::success($res);
        } catch (\RuntimeException $e) {
            return ResponseEnvelope::error([
                ['code' => 'restore_rejected', 'field' => null, 'message' => $e->getMessage()],
            ], 422);
        }
    }
}
