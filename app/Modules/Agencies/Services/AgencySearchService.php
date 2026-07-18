<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Database\Eloquent\Builder;

class AgencySearchService
{
    public function publicQuery(array $filters = []): Builder
    {
        $query = Agency::query()->publicVisible();

        return $this->applyFilters($query, $filters);
    }

    public function adminQuery(array $filters = []): Builder
    {
        return $this->applyFilters(Agency::query(), $filters);
    }

    public function applyFilters(Builder $query, array $filters): Builder
    {
        $query->search($filters['search'] ?? null);
        $query->byLocation($filters['department'] ?? null, $filters['province'] ?? null, $filters['district'] ?? null);

        foreach (['status', 'source', 'size'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (array_key_exists('operations_center', $filters) && $filters['operations_center'] !== '' && $filters['operations_center'] !== null) {
            $query->where('is_operations_center', filter_var($filters['operations_center'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('moved', $filters) && $filters['moved'] !== '' && $filters['moved'] !== null) {
            $query->where('has_moved', filter_var($filters['moved'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['updated_after'])) {
            $query->whereDate('updated_at', '>=', $filters['updated_after']);
        }

        if (!empty($filters['with_trashed'])) {
            $query->withTrashed();
        }

        if (!empty($filters['without_coordinates'])) {
            $query->whereNull('latitude')->whereNull('longitude');
        }

        if (!empty($filters['without_phone'])) {
            $query->whereNull('phone');
        }

        if (!empty($filters['under_review'])) {
            $query->where('status', AgencyStatus::UnderReview->value);
        }

        return $query;
    }
}
