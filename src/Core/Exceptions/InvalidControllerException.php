<?php

namespace Merlin\Core\Exceptions;

use Merlin\Core\Exception;

/**
 * Exception thrown when a controller class is found but is invalid (e.g. does not extend the base Controller class).
 */
class InvalidControllerException extends Exception
{

}
