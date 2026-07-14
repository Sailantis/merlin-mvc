<?php
namespace Merlin\Tests\Mvc\PhpThunder;

use Merlin\Core\Controller;

class IndexController extends Controller
{
    public function indexAction(): string
    {
        return 'php-thunder-home';
    }
}