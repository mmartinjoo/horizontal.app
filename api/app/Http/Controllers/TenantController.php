<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Enums\User\Role;
use Symfony\Component\HttpFoundation\Response;

class TenantController
{
    public function store(Request $request)
    {
        $request->validate([
            'company_name' => ['required', 'string'],
            'subdomain' => ['required', 'string', 'ascii', 'lowercase'],
            'country' => ['required', 'string'],
            'has_native_content' => ['sometimes', 'bool'],
            'admin_user_email' => ['required', 'email'],
            'admin_user_name' => ['required', 'string'],
            'questions_per_month' => ['required', 'numeric', 'in:150,250,500'],
            'data_retention' => ['required', 'numeric', 'in:3,6,12'],
            'number_of_seats' => ['required', 'numeric', 'lte:50'],
        ]);

        $tenant = Tenant::create([
            'company' => $request->get('company_name'),
            'country' => $request->get('country'),
            'questions_per_month' => $request->get('questions_per_month'),
            'data_retention' => $request->get('data_retention'),
            'number_of_seats' => $request->get('number_of_seats'),
        ]);

        $tenant->createDomain($request->get('subdomain') . '.horizontal.app');

        if (App::isLocal()) {
            $tenant->createDomain($request->get('subdomain') . '.localhost');
            $tenant->createDomain($request->get('subdomain') . '-horizontal.loca.lt');
            $tenant->createDomain($request->get('subdomain') . '.nginx');
        }

        tenancy()->initialize($tenant);
        User::create([
            'email' => $request->get('admin_user_email'),
            'name' => $request->get('admin_user_name'),
            'role' => Role::Admin,
        ]);
        tenancy()->end();

        return response()->json([
            'tenant' => $tenant,
        ], Response::HTTP_CREATED);
    }

    public function show(Tenant $tenant)
    {
        return [
            'id' => $tenant->id,
            'company' => $tenant->company,
            'graph_db_connection' => [
                'host' => $tenant->graph_db_host,
                'port' => $tenant->graph_db_port,
                'user' => $tenant->graph_db_user,
                'password' => decrypt($tenant->graph_db_password),
                'scheme' => $tenant->graph_db_scheme,
            ],
            'domains' => $tenant->domains->pluck('domain'),
        ];
    }
}
