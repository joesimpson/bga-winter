<?php
namespace Bga\Games\winter\Exceptions;
use Bga\Games\winter\Game;

class UserException extends \BgaUserException
{
    protected $code;

    public function __construct($code,$str)
    {
        $this->code = $code;
        parent::__construct($str);
    }
}
?>
