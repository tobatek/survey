<?php

namespace App\Domains\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Roles myAMETA Survey — FSD §5.2. Kode role bersifat tetap (dipakai di
// Policy/Gate), bukan sekadar label yang bebas diubah admin.
class Role extends Model
{
    use HasUuids;

    public const LPM_ADMIN = 'lpm_admin';

    public const SURVEY_OPERATOR = 'survey_operator';

    public const EXECUTIVE_VIEWER = 'executive_viewer';

    public const TECHNICAL_ADMIN = 'technical_admin';

    public const STUDENT_RESPONDENT = 'student_respondent';

    public const EMPLOYEE_RESPONDENT = 'employee_respondent';

    public const RAW_DATA_ANALYST = 'raw_data_analyst';

    protected $fillable = ['code', 'name'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
