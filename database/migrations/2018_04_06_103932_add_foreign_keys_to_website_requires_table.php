<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToWebsiteRequiresTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('website_requires', function (Blueprint $table) {
            $table->foreign('website_id')->references('id')->on('websites')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('website_requires', function (Blueprint $table) {
            $table->dropForeign(['website_id']);
        });
    }
}