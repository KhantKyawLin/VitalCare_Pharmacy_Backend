<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Permission;
use App\Models\Role;

$permissions = Permission::all();
echo "PERMISSIONS:\n";
foreach($permissions as $p) {
    echo "- {$p->name} ({$p->description})\n";
}

$roles = Role::with('permissions')->get();
echo "\nROLES:\n";
foreach($roles as $r) {
    echo "- {$r->name}: " . $r->permissions->pluck('name')->implode(', ') . "\n";
}
