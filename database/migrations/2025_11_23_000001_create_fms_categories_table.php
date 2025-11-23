<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('fms_categories', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->string('corpId', 10)->index();
			$table->string('companyName', 100)->index();
			$table->string('empCode', 20)->index();
			$table->string('fullName', 200);
			$table->string('fileCategory', 50)->index();
			$table->timestamps();

			$table->index(['corpId', 'companyName', 'fileCategory']);
			$table->index(['corpId', 'companyName', 'empCode']);
			$table->unique(['corpId', 'companyName', 'empCode', 'fileCategory'], 'uniq_category_per_employee');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('fms_categories');
	}
};
