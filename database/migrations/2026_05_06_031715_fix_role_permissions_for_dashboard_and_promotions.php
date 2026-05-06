<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Role;
use App\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Grant dashboard.view to staff
        $staff = Role::where('name', 'staff')->first();
        $pharmacist = Role::where('name', 'pharmacist')->first();
        
        $viewDash = Permission::where('name', 'dashboard.view')->first();
        $promoCrud = Permission::where('name', 'promotions.crud')->first();
        
        if ($staff && $viewDash) {
            $staff->permissions()->syncWithoutDetaching([$viewDash->id]);
        }

        if ($staff && $promoCrud) {
            $staff->permissions()->syncWithoutDetaching([$promoCrud->id]);
        }

        // Pharmacist already has these in seeder but let's ensure
        if ($pharmacist && $viewDash) {
            $pharmacist->permissions()->syncWithoutDetaching([$viewDash->id]);
        }
    }

    public function down(): void
    {
        // No need to reverse as these are additive permissions for basic functionality
    }
};
