<?php
/**
 * Base Resource - Abstract base for all API resources
 *
 * Provides common fields and structure for consistent API responses.
 */

namespace App\Http\Resources\Base;

/**
 * Abstract base resource with common fields for all API responses.
 *
 * Common fields included:
 * - id (integer) - Primary key
 * - created_at (datetime) - Creation timestamp
 * - updated_at (datetime) - Last update timestamp
 * - data (array) - Main resource data
 * - pagination (array) - Pagination metadata
 * - sparse_fieldsets (array) - Optional sparse field selection
 */

abstract class BaseResource
{
    /**
     * Common fields that all resources inherit
     */
    protected array $commonFields = [
        'id' => null,
        'created_at' => null,
        'updated_at' => null,
        'data' => null,
        'pagination' => null,
        'sparse_fieldsets' => null,
    ];

    /**
     * Constructor - call super to initialize common properties
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get common fields from the resource model
     */
    protected function getCommonFields(): array
    {
        return $this->commonFields;
    }

    /**
     * Add common fields to the resource data
     */
    protected function addCommonFields(array $fields): void
    {
        $this->data = array_merge($this->data, $fields);
    }

    /**
     * Add pagination metadata
     */
    protected function addPagination(array $pagination): void
    {
        $this->pagination = $pagination;
    }

    /**
     * Add sparse fieldsets for selective field retrieval
     */
    protected function addSparseFieldsets(array $sparseFieldsets): void
    {
        $this->sparseFieldsets = $sparseFieldsets;
    }

    /**
     * Get all fields (common + data)
     */
    public function getAllFields(): array
    {
        return array_merge($this->commonFields, $this->data);
    }

    /**
     * Serialize resource to array
     */
    public function serialize(): array
    {
        return $this->getAllFields();
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->serialize(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get resource ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get creation timestamp
     */
    public function getCreatedAt(): ?
    {
        return $this->created_at;
    }

    /**
     * Get last update timestamp
     */
    public function getUpdatedAt(): ?
    {
        return $this->updated_at;
    }

    /**
     * Get raw data from model
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Add common fields from model
     */
    public function addModelData(array $modelData): void
    {
        $this->addCommonFields($modelData);
    }

    /**
     * Add pagination metadata
     */
    public function addPagination(array $pagination): void
    {
        $this->addPagination($pagination);
    }

    /**
     * Add sparse fieldsets
     */
    public function addSparseFieldsets(array $sparseFieldsets): void
    {
        $this->addSparseFieldsets($sparseFieldsets);
    }
}
