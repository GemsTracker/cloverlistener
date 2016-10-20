<?php

/**
 *
 * @package    Gems
 * @subpackage Clover
 * @author     Matijs de Jong <mjong@magnafacta.nl>
 * @copyright  Copyright (c) 2016 Erasmus MC
 * @license    New BSD License
 */

namespace Gems\Clover;

use Zalt\Loader\Target\TargetInterface;
use Zalt\Loader\Target\TargetTrait;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Adapter\Driver\StatementInterface;

/**
 *
 *
 * @package    Gems
 * @subpackage Clover
 * @copyright  Copyright (c) 2016 Erasmus MC
 * @license    New BSD License
 * @since      Class available since version 1.0.0 Oct 18, 2016 6:39:36 PM
 */
class Installer implements ApplicationInterface, TargetInterface
{
    use TargetTrait;

    /**
     *
     * @var AdapterInterface
     */
    protected $db;

    /**
     * Should be called after answering the request to allow the Target
     * to check if all required registry values have been set correctly.
     *
     * @return boolean False if required values are missing.
     */
    public function checkRegistryRequestsAnswers()
    {
        return $this->db instanceof AdapterInterface;
    }

    /**
     * Start the main application
     *
     * @return void
     */
    public function run()
    {
        $dbConfig = dirname(__DIR__) . '/config/db';

        $dir = new \DirectoryIterator($dbConfig);

        foreach ($dir as $file) {
            if ($file instanceof \DirectoryIterator) {
                if ($file->isFile()) {
                    $queries = file_get_contents($file->getPathname());

                    // TODO: Export SQL Parser to Zalt
                    foreach (explode(";", $queries) as $query) {
                        if (trim($query)) {
                            $stmt = $this->db->query($query);

                            if ($stmt instanceof StatementInterface) {
                                $result = $stmt->execute();

                                $fileName = $file->getBasename();

                                // TODO: logging instead of echo
                                echo "Executed script: $fileName successfully.\n";
                            }
                        }
                    }
                }
            }
        }

        // TODO: logging instead of echo
        echo "Installation completed!\n";
    }
}
