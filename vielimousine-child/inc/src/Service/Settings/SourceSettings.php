<?php
declare(strict_types=1);

namespace Vie\Service\Settings;

final class SourceSettings
{
    private const OPTION_KEY = 'vie_order_sources';

    /** @return array<int, array{value: string, label: string}> */
    public function all(): array
    {
        $raw = get_option(self::OPTION_KEY, '');
        $data = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        } elseif (is_array($raw)) {
            $data = $raw;
        }
        $sources = $this->sanitizeList($data);
        return $sources !== [] ? $sources : $this->defaults();
    }

    /** @return array<int, array{value: string, label: string}> */
    public function defaults(): array
    {
        return [
            ['value' => 'website', 'label' => 'Website'],
            ['value' => 'admin',   'label' => 'Admin'],
            ['value' => 'phone',   'label' => 'Phone'],
            ['value' => 'walkin',  'label' => 'Walk-in'],
        ];
    }

    /**
     * @param array<int, array{value: string, label: string}> $sources
     * @return array<int, array{value: string, label: string}>
     */
    public function update(array $sources): array
    {
        $clean = $this->sanitizeList($sources);
        update_option(self::OPTION_KEY, wp_json_encode($clean), false);
        return $clean;
    }

    public function labelFor(string $value): string
    {
        foreach ($this->all() as $source) {
            if ($source['value'] === $value) {
                return $source['label'];
            }
        }
        return $value;
    }

    /** @return array<int, array{value: string, label: string}> */
    private function sanitizeList(array $data): array
    {
        $clean = [];
        $seen  = [];
        foreach ($data as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $value = sanitize_key((string) ($entry['value'] ?? ''));
            $label = sanitize_text_field((string) ($entry['label'] ?? ''));
            if ($value === '' || strlen($value) > 50 || $label === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $clean[] = ['value' => $value, 'label' => $label];
        }
        return $clean;
    }
}
