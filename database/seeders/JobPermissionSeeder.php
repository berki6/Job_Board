<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class JobPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $job_seeker = Role::create(['name' => 'job_seeker']);
        $employer = Role::create(['name' => 'employer']);
        $admin = Role::create(['name' => 'admin']);

        $permissions = [
            'create_jobs',
            'edit_jobs',
            'delete_jobs',
            'apply_jobs',
            'post_jobs',
            'update',
            'create-job-alert',
            'delete-job-alert',
            'view-job-seeker-dashboard',
            'view-employer-dashboard',
            'view-admin-dashboard',
            'update-profile',
            'update-skills',
            'read_notification',
            'create_payment',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $admin->givePermissionTo(['create_jobs', 'edit_jobs', 'delete_jobs', 'update', 'view-admin-dashboard']);
        $employer->givePermissionTo(['create_jobs', 'edit_jobs', 'delete_jobs', 'post_jobs', 'update-profile', 'update', 'read_notification', 'create_payment', 'view-employer-dashboard']);
        $job_seeker->givePermissionTo(['apply_jobs', 'update-profile', 'update-skills', 'create-job-alert', 'delete-job-alert', 'read_notification', 'create_payment', 'view-job-seeker-dashboard']);

        // Assign roles to users (example)
        $user = User::find(1); // Change this to the user ID you want to assign
        if ($user) {
            $user->assignRole('admin'); // Assign the admin role to this user
        }
    }
}
