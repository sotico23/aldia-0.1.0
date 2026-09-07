<?php
/**
 * User Minimal Resource - Lightweight representation for nested relations
 */

namespace App\Http\Resources\User;

use App\Http\Resources\Base\BaseResource;

class UserMinimalResource extends BaseResource
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get minimal user data
     */
    public function getMinimalData(): array
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
