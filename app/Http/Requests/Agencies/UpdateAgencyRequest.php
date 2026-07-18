<?php

namespace App\Http\Requests\Agencies;

class UpdateAgencyRequest extends StoreAgencyRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['code'][3] = 'unique:agencies,code,'.$this->route('agency')?->id;

        return $rules;
    }
}
