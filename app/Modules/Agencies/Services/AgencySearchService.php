<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AgencySearchService
{
    /** @return Builder<Agency> */
    public function publicQuery(array $filters = []): Builder
    {
        $query = Agency::query()->publicVisible();

        return $this->applyFilters($query, $filters);
    }

    /** @return Builder<Agency> */
    public function adminQuery(array $filters = []): Builder
    {
        return $this->applyFilters(Agency::query(), $filters);
    }

    /**
     * @param  Builder<Agency>  $query
     * @return Builder<Agency>
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        $trash = (string) ($filters['trash'] ?? $filters['with_trashed'] ?? '');
        if ($trash === 'only') {
            $query->onlyTrashed();
        } elseif (in_array($trash, ['1', 'with'], true)) {
            $query->withTrashed();
        }

        $query->search($filters['search'] ?? null);
        $query->byLocation($filters['department'] ?? null, $filters['province'] ?? null, $filters['district'] ?? null);

        foreach (['status', 'source', 'size', 'old_name'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['category'])) {
            $query->whereRaw(
                "regexp_replace(lower(unaccent(coalesce(classification_category, ''))), '[[:space:]\-_/]+', ' ', 'g') = ?",
                [$this->normalizeCategoryValue((string) $filters['category'])],
            );
        }

        if (array_key_exists('operations_center', $filters) && $filters['operations_center'] !== '' && $filters['operations_center'] !== null) {
            $query->where('is_operations_center', filter_var($filters['operations_center'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('moved', $filters) && $filters['moved'] !== '' && $filters['moved'] !== null) {
            $query->where('has_moved', filter_var($filters['moved'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['updated_after'])) {
            $query->whereDate('updated_at', '>=', $filters['updated_after']);
        }

        if (! empty($filters['without_coordinates'])) {
            $query->whereNull('latitude')->whereNull('longitude');
        }

        if (! empty($filters['without_phone'])) {
            $query->whereNull('phone');
        }

        if (! empty($filters['under_review'])) {
            $query->where('status', AgencyStatus::UnderReview->value);
        }

        // Filtros de tres estados: '' no filtra, '1' exige valor y '0' exige
        // ausencia. Se comprueba también la cadena vacía porque estas columnas
        // son de texto y un '' guardado no significa "tiene chosen".
        $this->applyPresenceFilter($query, $filters, 'has_chosen_terrestre', 'texto_chosen_terrestre');
        $this->applyPresenceFilter($query, $filters, 'has_chosen_aereo', 'texto_chosen_aereo');
        $this->applyPresenceFilter($query, $filters, 'has_changed_name', 'old_name');

        return $query;
    }

    public function normalizeCategoryValue(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = Str::ascii(mb_strtolower($value));
        $value = str_replace(['-', '_', '/'], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return trim($value);
    }

    /**
     * @param  Builder<Agency>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyPresenceFilter(Builder $query, array $filters, string $filterKey, string $column): void
    {
        $value = $filters[$filterKey] ?? null;

        if ($value === null || $value === '') {
            return;
        }

        // filter_var acepta '1'/'0', 'true'/'false', 'si'/'no' vía la vista y
        // devuelve null para basura, en cuyo caso no se filtra.
        $wanted = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($wanted === null) {
            return;
        }

        if ($wanted) {
            $query->whereNotNull($column)->where($column, '!=', '');

            return;
        }

        $query->where(fn (Builder $inner) => $inner->whereNull($column)->orWhere($column, '=', ''));
    }
}
