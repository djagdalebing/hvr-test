<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCreatorProjectsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('creator_projects')) return;
        Schema::create('creator_projects', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('user_id')->index();
            $t->string('title', 200);
            $t->string('role', 200)->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->text('description')->nullable();
            $t->string('url', 500)->nullable();
            $t->string('image_path', 500)->nullable();
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('creator_projects');
    }
}
