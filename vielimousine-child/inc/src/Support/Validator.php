<?php
declare(strict_types=1);

namespace Vie\Support;

final class Validator
{
    private array $errors = [];
    private array $validated = [];

    private const MESSAGES = [
        'required'   => ':field là bắt buộc',
        'string'     => ':field phải là chuỗi ký tự',
        'int'        => ':field phải là số nguyên',
        'float'      => ':field phải là số',
        'bool'       => ':field phải là giá trị đúng/sai',
        'email'      => ':field không đúng định dạng email',
        'date'       => ':field không đúng định dạng ngày (YYYY-MM-DD)',
        'phone'      => ':field không hợp lệ',
        'min'        => ':field phải lớn hơn hoặc bằng :param',
        'max'        => ':field phải nhỏ hơn hoặc bằng :param',
        'max_string' => ':field không được vượt quá :param ký tự',
        'min_string' => ':field phải có ít nhất :param ký tự',
        'in'         => ':field phải là một trong: :param',
        'exists'     => ':field không tồn tại trong hệ thống',
        'unique'     => ':field đã tồn tại',
        'array'      => ':field phải là một danh sách',
        'min_items'  => ':field phải có ít nhất :param phần tử',
        'max_items'  => ':field không được vượt quá :param phần tử',
        'after'      => ':field phải sau :param',
    ];

    private function __construct(
        private readonly array $data,
        private readonly array $rules,
    ) {
    }

    public static function validate(array $data, array $rules): self
    {
        $instance = new self($data, $rules);
        $instance->run();
        return $instance;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        $expanded = $this->expandWildcardRules($this->rules, $this->data);

        foreach ($expanded as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->getNestedValue($this->data, $field);

            $isNullable = in_array('nullable', $rules, true);
            $isRequired = in_array('required', $rules, true);

            if ($isRequired && ($value === null || $value === '')) {
                $this->addError($field, 'required');
                continue;
            }

            if ($isNullable && ($value === null || $value === '')) {
                continue;
            }

            if (!$isRequired && $value === null) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }

                $parsed = $this->parseRule($rule);
                $passed = $this->applyRule($field, $value, $parsed['name'], $parsed['param']);

                if (!$passed) {
                    break;
                }
            }

            if (!$this->hasFieldError($field)) {
                $this->setNestedValue($this->validated, $field, $value);
            }
        }
    }

    private function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$name, $param] = explode(':', $rule, 2);
            return ['name' => $name, 'param' => $param];
        }
        return ['name' => $rule, 'param' => null];
    }

    private function applyRule(string $field, mixed $value, string $ruleName, ?string $param): bool
    {
        return match ($ruleName) {
            'string'    => $this->validateString($field, $value),
            'int'       => $this->validateInt($field, $value),
            'float'     => $this->validateFloat($field, $value),
            'bool'      => $this->validateBool($field, $value),
            'email'     => $this->validateEmail($field, $value),
            'date'      => $this->validateDate($field, $value),
            'phone'     => $this->validatePhone($field, $value),
            'min'       => $this->validateMin($field, $value, $param),
            'max'       => $this->validateMax($field, $value, $param),
            'in'        => $this->validateIn($field, $value, $param),
            'exists'    => $this->validateExists($field, $value, $param),
            'unique'    => $this->validateUnique($field, $value, $param),
            'array'     => $this->validateArray($field, $value),
            'min_items' => $this->validateMinItems($field, $value, $param),
            'max_items' => $this->validateMaxItems($field, $value, $param),
            'after'     => $this->validateAfter($field, $value, $param),
            default     => true,
        };
    }

    private function validateString(string $field, mixed $value): bool
    {
        if (!is_string($value)) {
            $this->addError($field, 'string');
            return false;
        }
        return true;
    }

    private function validateInt(string $field, mixed $value): bool
    {
        if (!is_int($value) && !is_numeric($value)) {
            $this->addError($field, 'int');
            return false;
        }
        return true;
    }

    private function validateFloat(string $field, mixed $value): bool
    {
        if (!is_numeric($value)) {
            $this->addError($field, 'float');
            return false;
        }
        return true;
    }

    private function validateBool(string $field, mixed $value): bool
    {
        if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
            $this->addError($field, 'bool');
            return false;
        }
        return true;
    }

    private function validateEmail(string $field, mixed $value): bool
    {
        if (!is_string($value) || !is_email($value)) {
            $this->addError($field, 'email');
            return false;
        }
        return true;
    }

    private function validateDate(string $field, mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $this->addError($field, 'date');
            return false;
        }
        $parts = explode('-', $value);
        if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            $this->addError($field, 'date');
            return false;
        }
        return true;
    }

    private function validatePhone(string $field, mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^[0-9+\-\s().]{7,20}$/', $value)) {
            $this->addError($field, 'phone');
            return false;
        }
        return true;
    }

    private function validateMin(string $field, mixed $value, ?string $param): bool
    {
        $min = (float) $param;
        if (is_string($value)) {
            if (mb_strlen($value) < $min) {
                $this->addError($field, 'min_string', $param);
                return false;
            }
        } elseif (is_numeric($value)) {
            if ((float) $value < $min) {
                $this->addError($field, 'min', $param);
                return false;
            }
        }
        return true;
    }

    private function validateMax(string $field, mixed $value, ?string $param): bool
    {
        $max = (float) $param;
        if (is_string($value)) {
            if (mb_strlen($value) > $max) {
                $this->addError($field, 'max_string', $param);
                return false;
            }
        } elseif (is_numeric($value)) {
            if ((float) $value > $max) {
                $this->addError($field, 'max', $param);
                return false;
            }
        }
        return true;
    }

    private function validateIn(string $field, mixed $value, ?string $param): bool
    {
        $allowed = explode(',', $param ?? '');
        if (!in_array((string) $value, $allowed, true)) {
            $this->addError($field, 'in', $param);
            return false;
        }
        return true;
    }

    private function validateExists(string $field, mixed $value, ?string $param): bool
    {
        // param format: table,column  e.g. "vie_hotel,id"
        $parts  = explode(',', $param ?? '');
        $table  = $parts[0] ?? '';
        $column = $parts[1] ?? 'id';

        global $wpdb;
        $fullTable = $wpdb->prefix . $table;
        $sql = $wpdb->prepare(
            "SELECT 1 FROM {$fullTable} WHERE {$column} = %s LIMIT 1",
            $value
        );
        if (!$wpdb->get_var($sql)) {
            $this->addError($field, 'exists');
            return false;
        }
        return true;
    }

    private function validateUnique(string $field, mixed $value, ?string $param): bool
    {
        // param format: table,column[,excludeId]  e.g. "vie_hotel,slug,5"
        $parts     = explode(',', $param ?? '');
        $table     = $parts[0] ?? '';
        $column    = $parts[1] ?? '';
        $excludeId = isset($parts[2]) && $parts[2] !== '' ? (int) $parts[2] : null;

        global $wpdb;
        $fullTable = $wpdb->prefix . $table;

        if ($excludeId !== null) {
            $sql = $wpdb->prepare(
                "SELECT 1 FROM {$fullTable} WHERE {$column} = %s AND id != %d LIMIT 1",
                $value,
                $excludeId
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT 1 FROM {$fullTable} WHERE {$column} = %s LIMIT 1",
                $value
            );
        }

        if ($wpdb->get_var($sql)) {
            $this->addError($field, 'unique');
            return false;
        }
        return true;
    }

    private function validateArray(string $field, mixed $value): bool
    {
        if (!is_array($value)) {
            $this->addError($field, 'array');
            return false;
        }
        return true;
    }

    private function validateMinItems(string $field, mixed $value, ?string $param): bool
    {
        if (is_array($value) && count($value) < (int) $param) {
            $this->addError($field, 'min_items', $param);
            return false;
        }
        return true;
    }

    private function validateMaxItems(string $field, mixed $value, ?string $param): bool
    {
        if (is_array($value) && count($value) > (int) $param) {
            $this->addError($field, 'max_items', $param);
            return false;
        }
        return true;
    }

    private function validateAfter(string $field, mixed $value, ?string $param): bool
    {
        if (!is_string($value) || !is_string($param)) {
            return true;
        }
        $valueTime = strtotime($value);
        $paramTime = strtotime($param);
        if ($valueTime === false || $paramTime === false) {
            return true;
        }
        if ($valueTime <= $paramTime) {
            $this->addError($field, 'after', $param);
            return false;
        }
        return true;
    }

    private function addError(string $field, string $rule, ?string $param = null): void
    {
        $messageKey = $rule;
        $template   = self::MESSAGES[$messageKey] ?? ':field không hợp lệ';
        $message    = str_replace([':field', ':param'], [$field, $param ?? ''], $template);

        $this->errors[] = [
            'code'    => 'validation_error',
            'field'   => $field,
            'message' => $message,
        ];
    }

    private function hasFieldError(string $field): bool
    {
        foreach ($this->errors as $error) {
            if ($error['field'] === $field) {
                return true;
            }
        }
        return false;
    }

    private function getNestedValue(array $data, string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $data;
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }
        return $value;
    }

    private function setNestedValue(array &$data, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $ref = &$data;
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $ref[$k] = $value;
            } else {
                if (!isset($ref[$k]) || !is_array($ref[$k])) {
                    $ref[$k] = [];
                }
                $ref = &$ref[$k];
            }
        }
    }

    private function expandWildcardRules(array $rules, array $data): array
    {
        $expanded = [];
        foreach ($rules as $field => $ruleString) {
            if (!str_contains($field, '*')) {
                $expanded[$field] = $ruleString;
                continue;
            }
            $segments = explode('.', $field);
            $paths    = $this->resolveWildcard($segments, $data, []);
            foreach ($paths as $path) {
                $expanded[$path] = $ruleString;
            }
        }
        return $expanded;
    }

    private function resolveWildcard(array $segments, mixed $data, array $prefix): array
    {
        if ($segments === []) {
            return [implode('.', $prefix)];
        }

        $current   = array_shift($segments);
        $paths     = [];

        if ($current === '*') {
            if (!is_array($data)) {
                return [];
            }
            foreach (array_keys($data) as $key) {
                $paths = array_merge(
                    $paths,
                    $this->resolveWildcard($segments, $data[$key], [...$prefix, (string) $key])
                );
            }
        } else {
            $next = is_array($data) && array_key_exists($current, $data) ? $data[$current] : null;
            $paths = $this->resolveWildcard($segments, $next, [...$prefix, $current]);
        }

        return $paths;
    }
}
