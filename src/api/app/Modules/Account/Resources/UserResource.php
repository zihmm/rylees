<?php

declare(strict_types=1);

namespace App\Modules\Account\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $organisation = $this->profile->organisation;

        return [
            'id' => $this->id,
            'username' => $this->username,
            'is_active' => $this->is_active,
            'activated_at' => $this->activated_at,
            'api_key' => $this->api_key,
            'profile' => [
                'id' => $this->profile->id,
                'firstname' => $this->profile->firstname,
                'lastname' => $this->profile->lastname,
            ],
            'organisation' => [
                'id' => $organisation->id,
                'slug' => $organisation->slug,
                'name' => $organisation->name,
                'street' => $organisation->street,
                'postcode' => $organisation->postcode,
                'city' => $organisation->city,
                'website' => $organisation->website,
                'email' => $organisation->email,
            ],
        ];
    }
}
