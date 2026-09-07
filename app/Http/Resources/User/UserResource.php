<?php
/**
 * User Resource - Handles User model operations
 */

namespace App\Http\Resources\User;

use App\Http\Resources\Base\BaseResource;

class UserResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get user data
     */
    public function getUserData(): array
    {
        return $this->getData();
    }

    /**
     * Get minimal user data (for nested relations)
     */
    public function getUserMinimalData(): array
    {
        return $this->addMinimalFields();
    }

    /**
     * Minimal user fields for nested relations
     */
    protected function addMinimalFields(): void
    {
        $this->addCommonFields([
            'id' => null,
            'created_at' => null,
            'updated_at' => null,
            'data' => [
                'username' => $this->user->username,
                'email' => $this->user->email,
                'fullname' => $this->user->fullname,
                'avatar_url' => $this->user->avatar_url,
                'role' => $this->user->role,
            ],
        ]);
    }
}
