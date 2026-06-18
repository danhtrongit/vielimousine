<?php
declare(strict_types=1);

namespace Vie\Service\Backup;

/**
 * Sao lưu/phục hồi các bảng vie_* bằng PHP thuần (không cần mysqldump/shell_exec).
 * Mọi thao tác bị giới hạn ở các bảng có tiền tố $wpdb->prefix.'vie_'.
 */
final class BackupService
{
    public static function allowPrefix(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'vie_';
    }

    public static function isAllowed(string $table): bool
    {
        return str_starts_with($table, self::allowPrefix());
    }

    /**
     * Thư mục chứa auto-snapshot, đã chặn truy cập HTTP (.htaccess deny-all + index.php).
     * Tránh để dump SQL (chứa PII/tài chính) tải được trực tiếp qua URL uploads.
     */
    public static function backupDir(): string
    {
        $dir = wp_upload_dir()['basedir'] . '/vie-backups';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        $ht = $dir . '/.htaccess';
        if (!is_file($ht)) {
            file_put_contents(
                $ht,
                "# Vielimousine backups — chặn truy cập HTTP\nRequire all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
            );
        }
        $idx = $dir . '/index.php';
        if (!is_file($idx)) {
            file_put_contents($idx, "<?php // Silence is golden.\n");
        }
        return $dir;
    }

    /** @return array<int,array{name:string,rows:int,size_mb:float}> */
    public static function listTables(): array
    {
        global $wpdb;
        $like = $wpdb->esc_like(self::allowPrefix()) . '%';
        $names = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT table_name AS n, ROUND((data_length+index_length)/1024/1024,2) AS mb
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name LIKE %s
                 ORDER BY table_name",
                $like
            ),
            ARRAY_A
        );
        $out = [];
        foreach ((array) $names as $r) {
            $name = (string) $r['n'];
            if (!self::isAllowed($name) || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                continue;
            }
            $out[] = [
                'name'    => $name,
                'rows'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$name}`"),
                'size_mb' => (float) $r['mb'],
            ];
        }
        return $out;
    }

    /** Sinh SQL dump cho các bảng (chỉ bảng được phép). */
    public static function export(array $tables): string
    {
        global $wpdb;
        $valid = array_values(array_filter($tables, static fn($t) => self::isAllowed((string) $t) && preg_match('/^[A-Za-z0-9_]+$/', (string) $t)));
        $out  = "-- Vielimousine backup " . gmdate('Y-m-d H:i:s') . " UTC\n";
        $out .= "-- tables: " . implode(', ', $valid) . "\n";
        $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $t) {
            $t = (string) $t;
            if (!self::isAllowed($t)) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', $t)) {
                continue; // tên bảng không hợp lệ — chặn injection qua identifier
            }
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$t}`", ARRAY_N);
            if (!$create || !isset($create[1])) {
                continue; // bảng không tồn tại
            }
            $out .= "DROP TABLE IF EXISTS `{$t}`;\n" . $create[1] . ";\n\n";

            $rows = $wpdb->get_results("SELECT * FROM `{$t}`", ARRAY_A);
            if ($rows) {
                $colList = '`' . implode('`,`', array_keys($rows[0])) . '`';
                $batch = [];
                foreach ($rows as $row) {
                    $vals = array_map(
                        static fn($v) => $v === null ? 'NULL' : "'" . esc_sql((string) $v) . "'",
                        array_values($row)
                    );
                    $batch[] = '(' . implode(',', $vals) . ')';
                    if (count($batch) >= 500) {
                        $out .= "INSERT INTO `{$t}` ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n";
                        $batch = [];
                    }
                }
                if ($batch) {
                    $out .= "INSERT INTO `{$t}` ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n";
                }
            }
            $out .= "\n";
        }

        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $out;
    }

    /**
     * Phục hồi từ SQL. Quét tên bảng trước: nếu có bảng ngoài allowlist -> ném RuntimeException,
     * KHÔNG thực thi gì. Thực thi nguyên khối qua mysqli_multi_query (server tự parse).
     * @return array{tables_restored:array<int,string>,statements:int,errors:array<int,string>}
     */
    public static function restore(string $sql): array
    {
        global $wpdb;

        $wpPrefix = $wpdb->prefix; // vd "wpte_"

        // (a) Tên bảng đứng sau các verb chạm bảng (kể cả không backtick), gồm DELETE.
        preg_match_all(
            '/(?:DROP\s+TABLE(?:\s+IF\s+EXISTS)?'
          . '|TRUNCATE(?:\s+TABLE)?'
          . '|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?'
          . '|ALTER\s+TABLE'
          . '|RENAME\s+TABLE'
          . '|INSERT(?:\s+IGNORE)?\s+INTO'
          . '|REPLACE(?:\s+INTO)?'
          . '|UPDATE(?:\s+LOW_PRIORITY)?(?:\s+IGNORE)?'
          . '|DELETE(?:\s+FROM)?'
          . ')\s+`?([A-Za-z0-9_]+)`?/i',
            $sql, $m
        );
        // (b) Mọi tên đích của RENAME ... TO ...
        preg_match_all('/RENAME\s+TABLE\s+`?[A-Za-z0-9_]+`?\s+TO\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m2);
        // (c) Lớp bảo vệ tổng quát (chống whack-a-mole): MỌI identifier backtick có tiền tố WP.
        //     Dump thật luôn backtick tên bảng; giá trị trong INSERT là chuỗi nháy đơn nên không khớp.
        preg_match_all('/`(' . preg_quote($wpPrefix, '/') . '[A-Za-z0-9_]+)`/i', $sql, $m3);

        $tables = array_values(array_unique(array_merge($m[1], $m2[1], $m3[1])));
        $bad = array_values(array_filter($tables, static fn($t) => !self::isAllowed($t)));
        if ($bad) {
            throw new \RuntimeException('Bảng ngoài phạm vi cho phép: ' . implode(', ', $bad));
        }

        // Allowlist DẠNG câu lệnh (positive): chặn các câu không chạm bảng vie_ nhưng vẫn nguy
        // hiểm (SELECT ... INTO OUTFILE, SET GLOBAL, GRANT, ...) mà bộ lọc tên bảng ở trên bỏ sót.
        self::assertStatementsAllowed($sql);

        $dbh = $wpdb->dbh; // mysqli
        $full = "SET FOREIGN_KEY_CHECKS=0;\n" . $sql . "\nSET FOREIGN_KEY_CHECKS=1;\n";
        $errors = [];
        $stmts = 0;

        if (!mysqli_multi_query($dbh, $full)) {
            $errors[] = mysqli_error($dbh) ?: 'multi_query failed';
        } else {
            do {
                $stmts++;
                if ($r = mysqli_store_result($dbh)) {
                    mysqli_free_result($r);
                }
                if (!mysqli_more_results($dbh)) {
                    break;
                }
            } while (mysqli_next_result($dbh));
            $e = mysqli_error($dbh); // câu lệnh lỗi (nếu có) làm next_result trả false
            if ($e !== '') {
                $errors[] = $e;
            }
        }
        $wpdb->flush();

        return ['tables_restored' => $tables, 'statements' => $stmts, 'errors' => $errors];
    }

    /**
     * Mỗi câu lệnh trong file restore phải khớp một dạng cho phép (verb đứng đầu).
     * Dump do export() sinh ra chỉ gồm: comment, SET NAMES, SET FOREIGN_KEY_CHECKS,
     * DROP TABLE IF EXISTS, CREATE TABLE, INSERT INTO. Cho thêm TRUNCATE TABLE.
     * @throws \RuntimeException nếu có câu lệnh ngoài danh sách.
     */
    private static function assertStatementsAllowed(string $sql): void
    {
        $allowed = [
            '/^SET\s+NAMES\b/i',
            '/^SET\s+FOREIGN_KEY_CHECKS\b/i',
            '/^DROP\s+TABLE\s+IF\s+EXISTS\b/i',
            '/^CREATE\s+TABLE\b/i',
            '/^INSERT(?:\s+IGNORE)?\s+INTO\b/i',
            '/^TRUNCATE(?:\s+TABLE)?\b/i',
        ];
        foreach (self::splitStatements($sql) as $stmt) {
            $ok = false;
            foreach ($allowed as $re) {
                if (preg_match($re, $stmt)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $preview = substr((string) preg_replace('/\s+/', ' ', $stmt), 0, 60);
                throw new \RuntimeException('Câu lệnh không thuộc danh sách cho phép: ' . $preview);
            }
        }
    }

    /**
     * Tách SQL thành từng câu lệnh, tôn trọng chuỗi nháy đơn, định danh backtick và comment
     * (dòng "--" / "#" và khối). Không split theo ';' nằm trong chuỗi/định danh.
     * @return array<int,string>
     */
    private static function splitStatements(string $sql): array
    {
        $stmts = [];
        $buf   = '';
        $len   = strlen($sql);
        $inStr = false; // trong '...'
        $inTick = false; // trong `...`
        $i = 0;

        while ($i < $len) {
            $ch = $sql[$i];

            if ($inStr) {
                $buf .= $ch;
                if ($ch === '\\' && $i + 1 < $len) { // escape: nuốt ký tự kế
                    $buf .= $sql[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($ch === "'") {
                    if ($i + 1 < $len && $sql[$i + 1] === "'") { // '' = nháy đơn thoát
                        $buf .= "'";
                        $i += 2;
                        continue;
                    }
                    $inStr = false;
                }
                $i++;
                continue;
            }

            if ($inTick) {
                $buf .= $ch;
                if ($ch === '`') {
                    $inTick = false;
                }
                $i++;
                continue;
            }

            // Ngoài chuỗi/backtick: bỏ qua comment.
            if ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-'
                && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($ch === '#') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($ch === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2;
                continue;
            }

            if ($ch === "'") {
                $inStr = true;
                $buf  .= $ch;
                $i++;
                continue;
            }
            if ($ch === '`') {
                $inTick = true;
                $buf   .= $ch;
                $i++;
                continue;
            }

            if ($ch === ';') {
                $s = trim($buf);
                if ($s !== '') {
                    $stmts[] = $s;
                }
                $buf = '';
                $i++;
                continue;
            }

            $buf .= $ch;
            $i++;
        }

        $s = trim($buf);
        if ($s !== '') {
            $stmts[] = $s;
        }
        return $stmts;
    }
}
