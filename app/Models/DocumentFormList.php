<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentFormList extends Model
{
    use HasFactory;

    protected $table = 'document_form_list';

    protected $fillable = ['corp_id', 'name'];
}
