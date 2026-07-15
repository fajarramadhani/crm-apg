<?php

namespace App\Http\Requests\Api\V1;

class UpdateTicketRequest extends StoreTicketRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('ticket')) === true;
    }
}
