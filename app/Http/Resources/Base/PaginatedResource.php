<?php
/**
 * Paginated Resource - Wraps BaseResource with pagination support
 *
 * Adds pagination metadata (current_page, per_page, total, etc.)
 */

namespace App\Http\Resources\Base;

use Illuminate\Support\Facades\Pagination;

/**
 * Paginated resource wrapper around BaseResource
 *
 * Provides pagination metadata alongside the resource data.
 */

class PaginatedResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get pagination metadata
     */
    public function getPagination(): array
    {
        return [
            'current_page' => $this->pagination->currentPage(),
            'per_page' => $this->pagination->perPage(),
            'total' => $this->pagination->total(),
            'last_page' => $this->pagination->lastPage(),
            'has_next_page' => $this->pagination->hasNextPage(),
            'has_prev_page' => $this->pagination->hasPreviousPage(),
        ];
    }

    /**
     * Get combined resource and pagination data
     */
    public function getCombinedData(): array
    {
        return [
            'data' => $this->getCombinedFields(),
            'pagination' => $this->getPagination(),
        ];
    }

    /**
     * Get all fields including pagination
     */
    public function getAllFields(): array
    {
        return array_merge($this->getCommonFields(), $this->getCombinedFields());
    }
}
