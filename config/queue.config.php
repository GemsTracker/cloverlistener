<?php

/**
 * Return an array [action_name => action]. The actions are loaded using an overloader
 * looking in the sub-directories Clover\Queue\Action:
 *
 * <code>
 * [
 *  'action1' => 'MyAction', // creates new MyProject\Queue\Action\MyAction();
 *  'action2' => 'Zalt\Queue\Action\MyAction', // creates new Zalt\Queue\Action\MyAction();
 *
 *  // These all create new MyProject\Queue\Action\MyAction('x', 'y');
 *  'action3' => ['MyAction', 'x', 'y'],
 *  'action4' => [function () { return 'MyClass'; }, 'x', 'y'[,
 *  'action5' => [function () { return new \MyProject\Queue\Action\MyAction('x', 'y'); }),
 *  'action6' => new \MyProject\Queue\Action\MyAction('x', 'y'),
 * ]
 * </code>
 *
 * In all cases if the result object implements the TargetInterface, the variables requested
 * are loaded from the ServiceManager.
 *
 * @see \Zalt\Loader\ProjectOverloader->create()
 * @see \Zalt\Loader\Target\TargetInterface
 */

if (defined('GTIMPORT_EXEC_CMD')) {
    $exec = GTIMPORT_EXEC_CMD;
} else {
    $exec = 'php -f "' . dirname(dirname(__DIR__)) . '\\htdocs\\index.php" -- ';
}

return [
    'saveRespondent'  => ['SaveRespondentAction', $exec . 'respondent simple-api'],
    'saveAppointment' => ['SaveAppointmentAction', $exec . 'calendar simple-api'],
];