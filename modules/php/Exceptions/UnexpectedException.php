<?php
namespace Bga\Games\winter\Exceptions;
use Bga\Games\winter\Game;

class UnexpectedException extends \BgaVisibleSystemException
{
    protected $code;

    public function __construct($code,$str)
    {
        parent::__construct($str);
        $this->code = $code;
    }
}
?>
