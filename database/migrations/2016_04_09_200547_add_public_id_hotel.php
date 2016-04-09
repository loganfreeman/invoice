<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPublicIdHotel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('hotels', function (Blueprint $table) {
          $table->softDeletes();
          $table->unsignedInteger('public_id')->index();
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      if (Schema::hasColumn('hotels', 'deleted_at'))
  		{
  			Schema::table('hotels', function($table)
  			{
  				$table->dropColumn('deleted_at');
  			});
  		}

      if (Schema::hasColumn('hotels', 'public_id'))
      {
        Schema::table('hotels', function($table)
        {
          $table->dropColumn('public_id');
        });
      }
    }
}
