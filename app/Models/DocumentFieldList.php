<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentFieldList extends Model
{
    use HasFactory;

     protected $table = 'document_field_list';
    protected $fillable = ['corp_id', 'name'];

}
