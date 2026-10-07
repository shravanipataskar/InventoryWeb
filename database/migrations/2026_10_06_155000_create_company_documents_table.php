<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompanyDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('company_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->string('file_name');
            $table->string('storage_path');
            $table->unsignedBigInteger('file_size');
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('company_documents');
    }
}
