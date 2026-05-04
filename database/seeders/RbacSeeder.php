<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\SiteSetting;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        // --- 1. Create Permissions ---
        $permissionSlugs = [
            'dashboard.view'     => 'View admin dashboard statistics',
            'products.crud'      => 'Create, read, update, delete products',
            'categories.crud'    => 'Create, read, update, delete categories',
            'units.crud'         => 'Create, read, update, delete units',
            'orders.manage'      => 'View and update order/delivery/payment status',
            'promotions.crud'    => 'Create, read, update, delete promotions',
            'inventory.manage'   => 'Manage suppliers, purchases, stock movements',
            'users.view'         => 'View registered user data',
            'users.delete'       => 'Delete user accounts',
            'staff.manage'       => 'Create and manage staff accounts',
            'password.reset'     => 'Reset user passwords',
            'health_tips.crud'   => 'Create, read, update, delete health tips',
            'site_settings.manage' => 'Update pharmacy branding settings',
            'search.use'         => 'Use global product search',
            'feedback.create'    => 'Comment and rate health tips',
            'cart.use'           => 'Use shopping cart functionality',
            'wishlist.use'       => 'Use wishlist functionality',
            'orders.own'         => 'View and manage own orders',
            'contact.submit'     => 'Submit contact us messages',
            'activity_logs.view' => 'View activity audit logs',
        ];

        $permissions = [];
        foreach ($permissionSlugs as $slug => $desc) {
            $permissions[$slug] = Permission::updateOrCreate(
                ['name' => $slug],
                ['description' => $desc]
            );
        }

        // --- 2. Create Roles ---
        $adminRole = Role::updateOrCreate(
            ['name' => 'admin'],
            ['description' => 'Pharmacy administrator with full access']
        );

        $staffRole = Role::updateOrCreate(
            ['name' => 'staff'],
            ['description' => 'Pharmacy staff with limited access']
        );

        $pharmacistRole = Role::updateOrCreate(
            ['name' => 'pharmacist'],
            ['description' => 'Pharmacist with health tips management access']
        );

        $userRole = Role::updateOrCreate(
            ['name' => 'user'],
            ['description' => 'Registered customer']
        );

        // --- 3. Assign Permissions ---

        // Admin gets ALL permissions
        $adminRole->permissions()->sync(
            Permission::all()->pluck('id')->toArray()
        );

        // Staff permissions (Focused on Sales and Inventory lookup)
        $staffPerms = [
            'products.crud', 
            'categories.crud', 
            'units.crud', 
            'orders.manage', 
            'inventory.manage'
        ];
        $staffRole->permissions()->sync(
            Permission::whereIn('name', $staffPerms)->pluck('id')->toArray()
        );

        // Pharmacist permissions (Head of Operations - Health + Inventory)
        $pharmacistPerms = [
            'dashboard.view',
            'products.crud', 
            'categories.crud', 
            'units.crud', 
            'inventory.manage',
            'health_tips.crud',
            'promotions.crud',
            'orders.manage'
        ];
        $pharmacistRole->permissions()->sync(
            Permission::whereIn('name', $pharmacistPerms)->pluck('id')->toArray()
        );

        // User permissions
        $userPerms = ['search.use', 'feedback.create', 'cart.use', 'wishlist.use', 'orders.own', 'contact.submit'];
        $userRole->permissions()->sync(
            Permission::whereIn('name', $userPerms)->pluck('id')->toArray()
        );

        // --- 4. Default Site Settings ---
        $defaults = [
            'site_name'     => 'Vital Care Pharmacy',
            'site_logo'     => null,
            'primary_color' => '#a3c93a',
            'accent_color'  => '#8ab52e',
        ];

        foreach ($defaults as $key => $value) {
            SiteSetting::set($key, $value);
        }

        // --- 5. Create Default Users ---
        // Admin
        \App\Models\User::updateOrCreate(
            ['email' => 'admin@vitalcare.com'],
            [
                'name'     => 'System Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role'     => 'admin',
                'role_id'  => $adminRole->id,
                'gender'   => 'male',
                'phone'    => '09123456789',
                'address'  => 'Pharmacy Headquarters',
            ]
        );

        // Customer
        \App\Models\User::updateOrCreate(
            ['email' => 'customer@vitalcare.com'],
            [
                'name'     => 'John Customer',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role'     => 'user',
                'role_id'  => $userRole->id,
                'gender'   => 'male',
                'phone'    => '09777777777',
                'address'  => '123 Health Street, Wellness City',
            ]
        );

        // Staff
        \App\Models\User::updateOrCreate(
            ['email' => 'staff@vitalcare.com'],
            [
                'name'     => 'Sarah Staff',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role'     => 'staff',
                'role_id'  => $staffRole->id,
                'gender'   => 'female',
                'phone'    => '09555555555',
                'address'  => 'Staff Quarters, Vital Care',
            ]
        );

        // Pharmacist
        \App\Models\User::updateOrCreate(
            ['email' => 'pharmacist@vitalcare.com'],
            [
                'name'     => 'Dr. Phil Pharmacist',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role'     => 'pharmacist',
                'role_id'  => $pharmacistRole->id,
                'gender'   => 'male',
                'phone'    => '09888888888',
                'address'  => 'Wellness Plaza, Suite 402',
            ]
        );

        $this->command->info('RBAC roles, permissions, site settings, and default Admin account seeded successfully!');
    }
}
