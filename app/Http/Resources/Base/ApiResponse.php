<?php
/**
 * API Response - Standard envelope for API responses
 *
 * Wraps the main data with metadata (status, message, timestamps, etc.)
 */

namespace App\Http\Resources\Base;

/**
 * Standard API response envelope
 *
 * Structure:
 * {
 *   "success": boolean,
 *   "data": object|null,
 *   "meta": object|null,
 *   "errors": array|null
 * }
 */

class ApiResponse
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->success = true;
        $this->data = null;
        $this->meta = null;
        $this->errors = null;
    }

    /**
     * Set successful response
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    /**
     * Set data payload
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Set meta information (pagination, timing, etc.)
     */
    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
    }

    /**
     * Set error responses
     */
    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    /**
     * Get the entire response array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'meta' => $this->meta,
            'errors' => $this->errors,
        ];
    }

    /**
     * Get only the data portion
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * Get only the meta portion
     */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    /**
     * Get only the errors portion
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }
}
