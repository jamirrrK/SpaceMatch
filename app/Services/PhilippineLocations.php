<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class PhilippineLocations
{
    private ?array $data = null;

    public function data(): array
    {
        return $this->data ??= json_decode(file_get_contents(resource_path('data/philippine-locations.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function find(?string $code): ?array
    {
        return $code ? ($this->data()['locations'][$code] ?? null) : null;
    }

    public function ancestors(string $code): array
    {
        $result = [];
        while ($item = $this->find($code)) {
            $result[] = $item;
            $code = $item['parent'];
        }
        return $result;
    }

    public function options(string $kind, ?string $parent): array
    {
        return collect($this->data()['locations'])->filter(function ($item) use ($kind, $parent) {
            $type = $item['type'];
            if ($kind === 'region') return $type === 'region';
            if (!$parent || !$this->find($parent)) return false;
            if ($kind === 'province') return in_array($type, ['province', 'special_geographic_area']) && $item['parent'] === $parent;
            if ($kind === 'city') return in_array($type, ['municipality', 'component_city', 'highly_urbanized_city', 'independent_component_city']) && $item['parent'] === $parent;
            if ($kind === 'barangay') {
                if ($type !== 'barangay') return false;
                return $item['parent'] === $parent || ($this->find($item['parent'])['parent'] ?? null) === $parent;
            }
            return false;
        })->sortBy('name')->values()->all();
    }

    public function validateSelection(array $data): void
    {
        $previous = null;
        foreach (['region_code' => ['region'], 'province_code' => ['province', 'special_geographic_area'], 'city_code' => ['municipality', 'component_city', 'highly_urbanized_city', 'independent_component_city'], 'barangay_code' => ['barangay']] as $key => $types) {
            if (empty($data[$key])) continue;
            $item = $this->find($data[$key]);
            $ancestors = $item ? array_column($this->ancestors($item['code']), 'code') : [];
            if (!$item || !in_array($item['type'], $types) || ($previous && !in_array($previous, $ancestors))) {
                throw ValidationException::withMessages([$key => 'Choose a valid location in the selected geographic hierarchy.']);
            }
            $previous = $item['code'];
        }
    }
}
