<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterItinerariesTableAddColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('itineraries', function($t)
  		{
        $t->unsignedInteger('user_id')->index();
        $t->unsignedInteger('account_id')->index();
        $t->unsignedInteger('public_id')->index();
        $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
  		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      if (Schema::hasColumn('itineraries', 'user_id'))
  		{
  			Schema::table('itineraries', function($table)
  			{
  				$table->dropColumn('user_id');
  			});
  		}

      if (Schema::hasColumn('itineraries', 'account_id'))
      {
        Schema::table('itineraries', function($table)
        {
          $table->dropColumn('account_id');
        });
      }

      if (Schema::hasColumn('itineraries', 'public_id'))
      {
        Schema::table('itineraries', function($table)
        {
          $table->dropColumn('public_id');
        });
      }

    }
}
