<?php

namespace App\Http\Requests\Agencies;

class UpdateAgencyRequest extends StoreAgencyRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['external_id'][3] = 'unique:agencies,external_id,'.$this->route('agency')?->id;

        return $rules;
    }
}
