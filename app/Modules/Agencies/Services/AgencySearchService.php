<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Database\Eloquent\Builder;

class AgencySearchService
{
    public function publicQuery(array $filters = []): Builder
    {
        $query = Agency::query()->where('status', AgencyStatus::Active->value);

        return $this->applyFilters($query, $filters);
    }

    public function adminQuery(array $filters = []): Builder
    {
        return $this->applyFilters(Agency::query(), $filters);
    }

    public function applyFilters(Builder $query, array $filters): Builder
    {
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $search = mb_strtolower($search);
            $query->where(function (Builder $sub) use ($search): void {
                foreach (['code', 'name', 'short_name', 'department', 'province', 'district', 'address', 'reference'] as $field) {
                    $sub->orWhereRaw("unaccent(lower($field)) ILIKE unaccent(?)", ['%'.$search.'%']);
                }
            });
        }

        foreach (['department', 'province', 'district', 'status', 'source'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
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
