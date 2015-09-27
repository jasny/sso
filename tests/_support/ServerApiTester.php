<?php

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
 *
 * @SuppressWarnings(PHPMD)
*/
class ServerApiTester extends \Codeception\Actor
{
    use _generated\ApiTesterActions;

   /**
    * Define custom actions here
    */

    public $defaultArgs = [];

    public function sendServerRequest($command, $extraArgs = array())
    {
        $args = $this->defaultArgs;
        $args['command'] = $command;

        foreach ($extraArgs as $key => $value) {
            $args[$key] = $value;
        }

        $this->sendPost('/', $args);
    }
}
