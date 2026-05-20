<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class CustomerValidation
{
    public static function createRules(): array
    {
        return [
            'phone'            => 'required|phone|unique:vie_customer,phone',
            'name'             => 'required|string|max:255',
            'email'            => 'nullable|email',
            'note'             => 'nullable|string',
            'vat_company_name' => 'nullable|string|max:255',
            'vat_tax_code'     => 'nullable|string|max:50',
            'vat_address'      => 'nullable|string|max:500',
            'vat_email'        => 'nullable|email',
        ];
    }

    public static function updateRules(int $id): array
    {
        return [
            'phone'            => 'nullable|phone|unique:vie_customer,phone,' . $id,
            'name'             => 'nullable|string|max:255',
            'email'            => 'nullable|email',
            'note'             => 'nullable|string',
            'vat_company_name' => 'nullable|string|max:255',
            'vat_tax_code'     => 'nullable|string|max:50',
            'vat_address'      => 'nullable|string|max:500',
            'vat_email'        => 'nullable|email',
        ];
    }
}
