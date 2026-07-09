<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Role;

class RoleController
{
    public function index(Request $request): void
    {
        Response::json(Role::all());
    }
}
