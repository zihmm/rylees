<?php

declare(strict_types=1);

namespace App\Modules\Customer\Resources;

use App\Modules\Customer\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
final class CustomerDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $organisation = $this->organisation;
        $mainContact = $this->mainContact;
        $industry = $this->industry;

        return [
            'id' => $this->id,
            'description' => $this->description,
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
            'industry' => $industry === null ? null : [
                'id' => $industry->id,
                'name' => $industry->name,
            ],
            'contacts' => $this->contacts->map(static fn ($contact): array => [
                'id' => $contact->id,
                'firstname' => $contact->firstname,
                'lastname' => $contact->lastname,
                'email' => $contact->email,
            ])->all(),
            'main_contact' => $mainContact === null ? null : [
                'id' => $mainContact->id,
                'firstname' => $mainContact->firstname,
                'lastname' => $mainContact->lastname,
                'email' => $mainContact->email,
            ],
            'projects' => $this->projects->map(static fn ($project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'key' => $project->key,
            ])->all(),
        ];
    }
}
