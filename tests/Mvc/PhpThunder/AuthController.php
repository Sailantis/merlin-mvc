<?php

namespace Merlin\Tests\Mvc\PhpThunder;

use Merlin\Core\Controller;

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