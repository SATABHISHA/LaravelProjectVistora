<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id', 'EmpCode', 'prefix', 'FirstName', 'MiddleName', 'LastName', 'MaritalStatus', 'DOB',
        'Gender', 'BloodGroup', 'Nationality', 'WorkEmail', 'Mobile', 'SkillType', 'Pan', 'Adhaar',
        'Passport', 'PassportExpiryDate', 'PersonalEmail', 'EmgContactName', 'EmgNumber', 'EmgContactRelation',
        'PmntAddress', 'PmntCountry', 'PmntState', 'PmntCity', 'PmntPincode',
        'CrntAddress', 'CrntCountry', 'CrntState', 'CrntCity',
        'SameAsPmntAddYN', 'DraftYN'
    ];
}
