<?php

namespace Tests\Feature;

use App\Http\Requests\Agencies\PreviewAgencyImportRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AgencyImportPreviewValidationTest extends TestCase
{
    public function test_preview_rejects_missing_required_fields(): void
    {
        $validator = Validator::make([], (new PreviewAgencyImportRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('url', $validator->errors()->toArray());
    }
}
