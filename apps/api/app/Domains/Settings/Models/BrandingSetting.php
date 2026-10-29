<?php

namespace App\Domains\Settings\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BrandingSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'institution_name',
        'primary_color',
        'login_title',
        'login_description',
        'footer_text',
        'logo_path',
        'favicon_path',
        'updated_by',
    ];
}
