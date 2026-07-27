<?php

namespace Azera\Tests\Mvc\PhpThunder;

use Azera\Core\Controller;

class AuthController extends Controller
{
    public function loginAction(): string
    {
        return 'auth-login';
    }

    public function logoutAction(): string
    {
        return 'auth-logout';
    }
}