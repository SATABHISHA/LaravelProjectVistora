<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'corp_id', 'name', 'doc_type', 'track_send_alert_yn', 'candidate_view_yn', 'candidate_edit_yn',
        'emp_view_yn', 'emp_edit_yn', 'mandatory_employee_yn', 'mandatory_candidate_yn',
        'mandatory_to_convert_emp_yn', 'mandatory_upcoming_join_yn', 'form_list', 'field_list', 'doc_upload'
    ];
}
