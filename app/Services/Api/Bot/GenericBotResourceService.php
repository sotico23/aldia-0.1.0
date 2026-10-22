<?php

namespace App\Services\Api\Bot;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GenericBotResourceService
{
    private const MAX_LIMIT = 100;

    public function paginate(string $modelClass, int $ownerId, array $filters = [], array $options = []): array
    {
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), self::MAX_LIMIT);
        $offset = max((int) ($filters['offset'] ?? 0), 0);

        /** @var Builder $query */
        $query = $modelClass::query();

        $this->applyTenantScope($query, $modelClass, $ownerId, $options);
        $this->applyFilters($query, $filters, $options);

        $total = (clone $query)->count();
        $items = $query
            ->orderBy($options['order_by'] ?? 'id', $options['order_dir'] ?? 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function find(string $modelClass, int $ownerId, int|string $id, array $options = []): ?Model
    {
        /** @var Builder $query */
        $query = $modelClass::query();
        $this->applyTenantScope($query, $modelClass, $ownerId, $options);

        if (! empty($options['with'])) {
            $query->with($options['with']);
        }

        return $query->find($id);
    }

    protected function applyTenantScope(Builder $query, string $modelClass, int $ownerId, array $options = []): void
    {
        if (! empty($options['no_tenant_scope'])) {
            return;
        }

        $tenantColumn = $options['tenant_column'] ?? 'owner_id';
        $query->where($tenantColumn, $ownerId);
    }

    protected function applyFilters(Builder $query, array $filters, array $options = []): void
    {
        if (! empty($options['search_fields']) && ! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $q) use ($options, $term) {
                foreach ($options['search_fields'] as $field) {
                    if (Str::contains($field, '.')) {
                        [$relation, $relField] = explode('.', $field, 2);
                        $q->orWhereHas($relation, fn (Builder $rq) => $rq->where($relField, 'like', $term));
                    } else {
                        $q->orWhere($field, 'like', $term);
                    }
                }
            });
        }

        $exactFilters = $options['exact_filters'] ?? [];
        foreach ($exactFilters as $filter) {
            if (array_key_exists($filter, $filters) && $filters[$filter] !== null && $filters[$filter] !== '') {
                $query->where($filter, $filters[$filter]);
            }
        }

        $boolFilters = $options['bool_filters'] ?? [];
        foreach ($boolFilters as $filter) {
            if (array_key_exists($filter, $filters) && $filters[$filter] !== null && $filters[$filter] !== '') {
                $query->where($filter, filter_var($filters[$filter], FILTER_VALIDATE_BOOLEAN));
            }
        }

        $dateFrom = $options['date_from'] ?? null;
        if ($dateFrom && ! empty($filters['desde'])) {
            $query->whereDate($dateFrom, '>=', $filters['desde']);
        } elseif ($dateFrom && ! empty($filters['fecha_desde'])) {
            $query->whereDate($dateFrom, '>=', $filters['fecha_desde']);
        }

        $dateTo = $options['date_to'] ?? null;
        if ($dateTo && ! empty($filters['hasta'])) {
            $query->whereDate($dateTo, '<=', $filters['hasta']);
        } elseif ($dateTo && ! empty($filters['fecha_hasta'])) {
            $query->whereDate($dateTo, '<=', $filters['fecha_hasta']);
        }

        if (! empty($options['where']) && is_array($options['where'])) {
            foreach ($options['where'] as $column => $value) {
                if ($value !== null && $value !== '') {
                    $query->where($column, $value);
                }
            }
        }
    }

    public function serialize(Model $model, array $fields = []): array
    {
        if (empty($fields)) {
            return $model->toArray();
        }

        $data = [];
        foreach ($fields as $key => $field) {
            if (is_int($key)) {
                $data[$field] = $model->{$field};
            } else {
                $data[$key] = is_callable($field) ? $field($model) : ($model->{$field} ?? null);
            }
        }

        return $data;
    }
}
