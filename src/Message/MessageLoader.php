<?php

/**
 *
 * @package    Gems\Clover
 * @subpackage Message
 * @author Matijs de Jong <mjong@magnafacta.nl>
 * @copyright  Expression project.copyright is undefined on line 14, column 18 in Templates/Scripting/PHPClass.php.
 * @license    No free license, do not copy
 */

namespace Gems\Clover\Message;

use Zalt\Loader\Target\TargetInterface;
use Zalt\Loader\Target\TargetTrait;

/**
 *
 * @package    Gems\Clover
 * @subpackage Message
 * @copyright  Expression project.copyright is undefined on line 26, column 18 in Templates/Scripting/PHPClass.php.
 * @license    No free license, do not copy
 * @since      Class available since version 1.8.1 Oct 21, 2016 2:33:48 PM
 */
class MessageLoader implements TargetInterface
{
    use TargetTrait;

    /**
     * A installation specific segment loading class map
     *
     * @var array Segment name => segment class
     */
    protected $_segmentClassMap;

    /**
     *
     * @var \Gems\HL7\Unserializer
     */
    protected $_unserializer;

    /**
     *
     * @var \Zalt\Loader\ProjectOverloader
     */
    protected $loader;

    /**
     * Initialize the segment class map
     */
    protected function _initSegmentClassMap()
    {
        $this->_segmentClassMap = [
            'AIL' => $this->loader->find('HL7\\Segment\\AILSegment'),
            'EVN' => $this->loader->find('HL7\\Segment\\EVNSegment'),
            'MSA' => $this->loader->find('HL7\\Segment\\MSASegment'),
            'MRG' => $this->loader->find('HL7\\Segment\\MRGSegment'),
            'MSH' => $this->loader->find('HL7\\Segment\\MSHSegment'),
            'NTE' => $this->loader->find('HL7\\Segment\\NTESegment'),
            'PID' => $this->loader->find('HL7\\Segment\\PIDSegment'),
            'PV1' => $this->loader->find('HL7\\Segment\\PV1Segment'),
            'SCH' => $this->loader->find('HL7\\Segment\\SCHSegment'),
            'ZDB' => $this->loader->find('HL7\\Segment\\ZDBSegment'),
            ];
    }

    /**
     *  Initialize the unserializer
     */
    protected function _initUnserializer()
    {
        $this->_unserializer = $this->loader->create('HL7\\Unserializer');
    }

    /**
     * Called after the check that all required registry values
     * have been set correctly has run.
     *
     * @return void
     */
    public function afterRegistry()
    {
        $this->_initSegmentClassMap();
        $this->_initUnserializer();
    }

    public function getSegmentInfo()
    {
        $output = [];
        foreach ($this->_segmentClassMap as $key => $class) {
            $output[$key] = $class;
        }
        return $output;
    }

    /**
     *
     * @param string $hl7String A HL7 Payload
     * @param boolean $checkEencoding Should stringencoding be checked
     * @return \Gems\HL7\Node\Message
     */
    public function loadMessage($hl7String, $checkEncoding = true)
    {
        return $this->_unserializer->loadMessageFromString($hl7String, $this->_segmentClassMap, $checkEncoding);
    }
}
