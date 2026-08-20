<?php

namespace App\Http\Requests\Invitation;

use Illuminate\Foundation\Http\FormRequest;

class ProcessInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invitation = $this->route('invitation');
        return $invitation && $invitation->receiver_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [];
    }
}
