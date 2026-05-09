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
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('about_hero_image')->nullable();
            $table->string('about_story_image')->nullable();
            $table->string('about_title')->default('Your Health, Our Commitment.');
            $table->text('about_description')->nullable();
            $table->string('about_mission_title')->default('Our Mission');
            $table->text('about_mission_desc')->nullable();
            $table->string('about_vision_title')->default('Our Vision');
            $table->text('about_vision_desc')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'about_hero_image', 
                'about_story_image', 
                'about_title', 
                'about_description',
                'about_mission_title',
                'about_mission_desc',
                'about_vision_title',
                'about_vision_desc'
            ]);
        });
    }
};
