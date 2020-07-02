<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at                              |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Xavier Noguer <xnoguer@php.net>                              |
// +----------------------------------------------------------------------+
//
// $Id$

/**
* This class defines DICOM file elements
*
* @author   Xavier Noguer <xnoguer@php.net>
* @package  File_DICOM
*/
class File_DICOM_Element
{
    /**
    * Value Representations (DICOM Standard PS 3.5 Sect 6.2)
    * Bytes=0 => Undefined length.
    * Fixed=1 => Exact field length, otherwise max length.
    * each array contains:  Name, Bytes, Fixed
    * @var array
    */
    
    var $VR = [
        'AE' => ['Application Entity', 16, 0],
        'AS' => ['Age String', 4, 1],
        'AT' => ['Attribute Tag', 4, 1],
        'CS' => ['Code String', 16, 0],
        'DA' => ['Date', 8, 1],
        'DS' => ['Decimal String', 16, 0],
        'DT' => ['Date Time', 26, 0],
        'FL' => ['Floating Point Single', 4, 1],
        'FD' => ['Floating Point Double', 8, 1],
        'IS' => ['Integer String', 12, 0],
        'LO' => ['Long Strong', 64, 0],
        'LT' => ['Long Text', 10240, 0],
        'OB' => ['Other Byte String', 0, 0],
        'OW' => ['Other Word String', 0, 0],
        'PN' => ['Person Name', 64, 0],
        'SH' => ['Short String', 16, 0],
        'SL' => ['Signed Long', 4, 1],
        'SQ' => ['Sequence of Items', 0, 0],
        'SS' => ['Signed Short', 2 ,1],
        'ST' => ['Short Text', 1024, 0],
        'TM' => ['Time', 16, 0],
        'UI' => ['Unique Identifier UID', 64, 0],
        'UL' => ['Unsigned Long', 4, 1],
        'UN' => ['Unknown', 0, 0],
        'US' => ['Unsigned Short', 2, 1],
        'UT' => ['Unlimited Text', 0, 0],
    ];

    /**
    * Type of VR for this element
    * @var int
    */
    protected $vrType;

    /**
    * Element length
    * @var int
    */
    protected $value;

    /**
    * Element length
    * @var int
    */
    protected $code;

    /**
    * Element length
    * @var int
    */
    protected $length;

    /**
    * Complete header of this element. It might disappear in the future.
    * @var string
    */
    protected $header;

    /**
    * Group this element belongs to
    * @var int
    */
    protected $group;

    /**
    * Element identifier
    * @var int
    */
    protected $element;

    /**
    * Position inside the current field for the element
    * @var int
    */
    protected $offset;
 
    /**
    * Name for this element
    * @var int
    */
    protected $name;

    /**
    * Create DICOM file element from contents of the file given.
    * It assumes the element begins at the current position of the given 
    * file pointer.
    *
    * @param resource $IN       File handle for the file currently being parsed
    * @param array    &$dictref Reference to the dictionary of DICOM headers
    */
    public function File_DICOM_Element($IN, &$dictref)
    {
        // Tag holds group and element numbers in two bytes each.
        $offset  = ftell($IN);
        $group   = $this->readInt($IN, 2, 2);
        $element = $this->readInt($IN, 2, 2);
        // Next 4 bytes are either explicit VR or length (implicit VR).
        $length = $this->readLength($IN);
  
        // Go to record start, read bytes up to value field, store in header.
        $diff = ftell($IN) - $offset;
        fseek($IN, $offset);
        $header = fread($IN, $diff);
  
        if (isset($dictref[$group][$element])) {
            list($code, $numa, $name) = $dictref[$group][$element];
        } else {
            list($code, $numa, $name) = array("--", "UNKNOWN", "UNKNOWN");
        }
  
        // Read in the value field.  Certain fields need to be decoded.
        $value = '';
        if ($length > 0) {
            switch ($code) {
                // Decode ints and shorts.
                case 'UL':
                    $value = $this->readInt($IN, 4, $length);
                    break;
                case 'US':
                    $value = $this->readInt($IN, 2, $length);
                    break;
                case 'FL':
                    // TODO: test this
                    $value = $this->readFloat($IN, 4, $length);
                    break;
                case 'FD':
                    // TODO: test this
                    $value = $this->readFloat($IN, 8, $length);
                    break;
                // Binary data. Only save position. Is this right? 
                case 'OW':
                case 'OB':
                case 'OX':
                    $value = ftell($IN);
                    fseek($IN, $length, SEEK_CUR);
                    break;
                default: // Made it to here: Read bytes verbatim.
                    $value = fread($IN, $length);
                    break;
            }
        }
 
        // Fill in hash of values and return them.
        $this->value  = $value;
        $this->code   = $code;
        $this->length = $length;
        // why save header??
        $this->header  = $header;
        $this->element = $element;
        $this->group   = $group;
        $this->name    = $name;
        $this->offset  = $offset;
    }

    /**
    * Return the Value Field length, and length before Value Field.
    * Implicit VR: Length is 4 byte int.
    * Explicit VR: 2 bytes hold VR, then 2 byte length.
    *
    * @param resource $IN File handle for the file currently being parsed
     *
    * @return integer The length for the current field
    */
    private function readLength($IN)
    {
        // Read 4 bytes into b0, b1, b2, b3.
        $buff = fread($IN, 4);
        if (strlen($buff) < 4) {
            return 0;
        }
        $b = unpack("C4", $buff);
        // Temp string to test for explicit VR
        $vrstr = pack("C", $b[1]) . pack("C", $b[2]);
        # Assume that this is explicit VR if b[1] and b[2] match a known VR code.
        # Possibility (prob 26/16384) exists that the two low order field length 
        # bytes of an implicit VR field will match a VR code.
  
        # DICOM PS 3.5 Sect 7.1.2: Data Element Structure with Explicit VR
        # Explicit VRs store VR as text chars in 2 bytes.
        # VRs of OB, OW, SQ, UN, UT have VR chars, then 0x0000, then 32 bit VL:
        #
        # +-----------------------------------------------------------+
        # |  0 |  1 |  2 |  3 |  4 |  5 |  6 |  7 |  8 |  9 | 10 | 11 |
        # +----+----+----+----+----+----+----+----+----+----+----+----+
        # |<Group-->|<Element>|<VR----->|<0x0000->|<Length----------->|<Value->
        #
        # Other Explicit VRs have VR chars, then 16 bit VL:
        #
        # +---------------------------------------+
        # |  0 |  1 |  2 |  3 |  4 |  5 |  6 |  7 |
        # +----+----+----+----+----+----+----+----+
        # |<Group-->|<Element>|<VR----->|<Length->|<Value->
        #
        # Implicit VRs have no VR field, then 32 bit VL:
        #
        # +---------------------------------------+
        # |  0 |  1 |  2 |  3 |  4 |  5 |  6 |  7 |
        # +----+----+----+----+----+----+----+----+
        # |<Group-->|<Element>|<Length----------->|<Value->
  
        foreach (array_keys($this->VR) as $vr) {
            if ($vrstr === $vr) {
                // Have a code for an explicit VR: Retrieve VR element
                list($name, $bytes, $fixed) = $this->VR[$vr];
                if (0 === $bytes) {
                    $this->vrType = FILE_DICOM_VR_TYPE_EXPLICIT_32_BITS;
                    // This is an OB, OW, SQ, UN or UT: 32 bit VL field.
                    // Have seen in some files length 0xffff here...
                    return $this->readInt($IN, 4, 4);
                }
                // This is an explicit VR with 16 bit length.
                $this->vrType = FILE_DICOM_VR_TYPE_EXPLICIT_16_BITS;

                return ($b[4] << 8) + $b[3];
            }
        }
        // Made it to here: Implicit VR, 32 bit length.
        $this->vrType = FILE_DICOM_VR_TYPE_IMPLICIT;

        return ($b[4] << 24) + ($b[3] << 16) + ($b[2] << 8) + $b[1];
    }

    /**
    * Read an integer field from a file handle
    * If $len > $bytes multiple values are read in and
    * stored as a string representation of an array.
    * This method will probably change in the future.
    *
    * @param resource $IN filehandle for the file currently being parsed
    * @param integer  $bytes Number of bytes for integer (2 => short, 4 => integer)
    * @param integer  $len   Optional total number of bytes on the field
     *
    * @return mixed integer value if $len == $bytes, an array of integer if $len > $bytes
    */
    private function readInt($IN, $bytes, $len)
    {
        $format = (2 === $bytes) ? "v" : "V";
  
        $buff = fread($IN, $len);
        if ($len === $bytes) {
            if (strlen($buff) > 0) {
                $val = unpack($format, $buff);
                return $val[''];
            }

            return '';
        } else {
            // Multiple values: Create array.
            // Change this!!!
            $vals = [];
            for ($pos = 0; $pos < $len; $pos += $bytes) {
                $unpacked = unpack("$format", substr($buff, $pos, $bytes));
                $vals[] = $unpacked[''];
            }
            $val = "[".join(", ", $vals)."]";

            return $val;
        }
    }

    /**
    * Read a float field from a file handle
    * If $len > $bytes multiple values are read in and
    * stored as a string representation of an array.
    * This method will probably change in the future.
    *
    * @param resource $IN filehandle for the file currently being parsed
    * @param integer  $bytes Number of bytes for float (4 => float, 8 => double)
    * @param integer  $len   Total number of bytes on the field
    *
    * @return mixed double value if $len == $bytes, an array of doubles if $len > $bytes
    */
    private function readFloat($IN, $bytes, $len)
    {
        $format = (4 === $bytes) ? 'f' : 'd';
  
        $buff = fread($IN, $len);
        if ($len === $bytes) {
            if (strlen($buff) > 0) {
                $val = unpack($format, $buff);

                return $val[''];
            }

            return '';
        } else {
            // Multiple values: Create array.
            // Change this!!!
            $vals = [];
            for ($pos = 0; $pos < $len; $pos += $bytes) {
                $unpacked = unpack("$format", substr($buff, $pos, $bytes));
                $vals[] = $unpacked[''];
            }
            $val = "[".join(", ", $vals)."]";

            return $val;
        }
    }

    /**
    * Retrieves the value field for this File_DICOM_Element
    *
    * @return mixed The value for this File_DICOM_Element
    */
    public function getValue()
    {
        return $this->value;
    }
}
