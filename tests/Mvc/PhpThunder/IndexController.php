<?php
namespace Azera\Tests\Mvc\PhpThunder;

use Azera\Core\Controller;

class IndexController extends Controller
{
    public function indexAction(): string
    {
        return 'php-thunder-home';
    }
}