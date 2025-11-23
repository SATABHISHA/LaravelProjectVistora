<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FmsCategory extends Model
{
	use HasFactory;

	protected $table = 'fms_categories';

	protected $fillable = [
		'corpId',
		'companyName',
		'empCode',
		'fullName',
		'fileCategory',
	];
}