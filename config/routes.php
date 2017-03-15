<?php
/*
 * Copy this file to your own config dir to modify the routes. When adding a command (handler)
 * make sure to add the command to the servicemanager. Uncomment the following block to do so:
 */

/*
$name = 'listener';
$classname = ucfirst($name);
$sm = $loader->getServiceManager();
$factory = $loader->serviceManagerFactory($classname);
$sm->setFactory($name, $factory);
*/

return array(
    array(
        'name' => 'listen',
        'route' => 'listen',
        'short_description' => 'Listen to HL7 queue.',        
        'defaults' => array(
            'command' => 'run',            
        ),
        'handler' => 'listener',
    ),
    array(
        'name' => 'install',
        'route' => 'install',
        'short_description' => 'Install the application.',        
        'defaults' => array(
            'command' => 'run',            
        ),
        'handler' => 'installer',
    ),
    array(
        'name' => 'queue',
        'route' => '[<command>] [--failed] [--from=]',
        'description' => 'Manipulate the message queue, using a command and optional flags. Default command is run.',
        'short_description' => 'Manipulate the message queue',
        'options_descriptions' => [
            'run'      => 'Run the queue',
            '',
            'rerun'    => 'Execute messages again, default will be rerun all messages. Use optionals flag to modify behaviour.',
            '--failed  Only rerun failed messages',
            '',
            'rebuild'  => 'Rebuild the queue, use optional flag to limit rebuilding to certain messages',
            '--from=<value>  Start from a message number or a date. For dates us yyyy-mm-dd format',
        ],
        'defaults' => array(
            'command' => 'run',            
        ),
        'handler' => 'queueProcessor',
    ),
);