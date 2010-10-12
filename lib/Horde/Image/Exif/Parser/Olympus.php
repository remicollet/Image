<?php
/**
 * @author   Michael J. Rubinsky <mrubinsk@horde.org>
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @package  Image
 */

/**
 * Exifer
 * Extracts EXIF information from digital photos.
 *
 * Copyright © 2003 Jake Olefsky
 * http://www.offsky.com/software/exif/index.php
 * jake@olefsky.com
 *
 * ------------
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details. http://www.gnu.org/copyleft/gpl.html
 */
class Horde_Image_Exif_Parser_Olympus extends Horde_Image_Exif_Parser_Base
{
    /**
     *
     * @param $tag
     * @return unknown_type
     */
    protected function _lookupTag($tag)
    {
        switch($tag) {
        case '0200': $tag = 'SpecialMode'; break;
        case '0201': $tag = 'JpegQual'; break;
        case '0202': $tag = 'Macro'; break;
        case '0203': $tag = 'Unknown1'; break;
        case '0204': $tag = 'DigiZoom'; break;
        case '0205': $tag = 'Unknown2'; break;
        case '0206': $tag = 'Unknown3'; break;
        case '0207': $tag = 'SoftwareRelease'; break;
        case '0208': $tag = 'PictInfo'; break;
        case '0209': $tag = 'CameraID'; break;
        case '0f00': $tag = 'DataDump'; break;
        default:     $tag = 'unknown: ' . $tag; break;
        }

        return $tag;
    }

    /**
     *
     * @param $type
     * @param $tag
     * @param $intel
     * @param $data
     * @return unknown_type
     */
    protected function _formatData($type, $tag, $intel, $data)
    {
        switch ($type) {
        case 'ASCII':
        case 'UNDEFINED':
            break;

        case 'URATIONAL':
        case 'SRATIONAL':
            $data = bin2hex($data);
            if ($intel) {
                $data = Horde_Image_Exif::intel2Moto($data);
            }
            $top = hexdec(substr($data, 8, 8));
            $bottom = hexdec(substr($data, 0, 8));
            if ($bottom) {
                $data = $top / $bottom;
            } elseif (!$top) {
                $data = 0;
            } else {
                $data = $top . '/' . $bottom;
            }

            switch ($tag) {
            case '0204':
                //DigitalZoom
                $data .= 'x';
                break;
            case '0205':
                //Unknown2
                $data = $top . '/' . $bottom;
                break;
            }
            break;

        case 'USHORT':
        case 'SSHORT':
        case 'ULONG':
        case 'SLONG':
        case 'FLOAT':
        case 'DOUBLE':
            $data = bin2hex($data);
            if ($intel) {
                $data = Horde_Image_Exif::intel2Moto($data);
            }
            $data = hexdec($data);

            switch ($tag) {
            case '0201':
                //JPEGQuality
                switch ($data) {
                case 1:  $data = 'SQ'; break;
                case 2:  $data = 'HQ'; break;
                case 3:  $data = 'SHQ'; break;
                default: $data = _("Unknown") . ': ' . $data; break;
                }
                break;
            case '0202':
                //Macro
                switch ($data) {
                case 0:  $data = 'Normal'; break;
                case 1:  $data = 'Macro'; break;
                default: $data = _("Unknown") . ': ' . $data; break;
                }
                break;
            }
            break;

        default:
            $data = bin2hex($data);
            if ($intel) {
                $data = Horde_Image_Exif::intel2Moto($data);
            }
            break;
        }

        return $data;
    }

    /**
     *
     * @param $block
     * @param $result
     * @param $seek
     * @param $globalOffset
     * @return unknown_type
     */
    public function parse($block, &$result, $seek, $globalOffset)
    {
        $intel = $result['Endien']=='Intel';
        $model = $result['IFD0']['Model'];

        // New header for new DSLRs - Check for it because the number of bytes
        // that count the IFD fields differ in each case.  Fixed by Zenphoto
        // 2/24/08
        $new = false;
        if (substr($block, 0, 8) == "OLYMPUS\x00") {
            $new = true;
        } elseif (substr($block, 0, 7) == "OLYMP\x00\x01" ||
                  substr($block, 0, 7) == "OLYMP\x00\x02") {
            $new = false;
        } else {
            // Header does not match known Olympus headers.
            // This is not a valid OLYMPUS Makernote.
            return false;
        }

        // Offset of IFD entry after Olympus header.
        $place = 8;
        $offset = 8;

        // Get number of tags (1 or 2 bytes, depending on New or Old makernote)
        $countfieldbits = $new ? 1 : 2;
        // New makernote repeats 1-byte value twice, so increment $place by 2
        // in either case.
        $num = bin2hex(substr($block, $place, $countfieldbits));
        $place += 2;
        if ($intel) {
            $num = Horde_Image_Exif::intel2Moto($num);
        }
        $ntags = hexdec($num);
        $result['SubIFD']['MakerNote']['MakerNoteNumTags'] = $ntags;

        //loop thru all tags  Each field is 12 bytes
        for ($i = 0; $i < $ntags; $i++) {
            //2 byte tag
            $tag = bin2hex(substr($block, $place, 2));
            $place += 2;
            if ($intel) {
                $tag = Horde_Image_Exif::intel2Moto($tag);
            }
            $tag_name = $this->_lookupTag($tag);

            //2 byte type
            $type = bin2hex(substr($block, $place, 2));
            $place += 2;
            if ($intel) {
                $type = Horde_Image_Exif::intel2Moto($type);
            }
            $this->_lookupType($type, $size);

            //4 byte count of number of data units
            $count = bin2hex(substr($block, $place, 4));
            $place += 4;
            if ($intel) {
                $count = Horde_Image_Exif::intel2Moto($count);
            }
            $bytesofdata = $size * hexdec($count);

            //4 byte value of data or pointer to data
            $value = substr($block, $place, 4);
            $place += 4;

            if ($bytesofdata <= 4) {
                $data = $value;
            } else {
                $value = bin2hex($value);
                if ($intel) {
                    $value = Horde_Image_Exif::intel2Moto($value);
                }
                //offsets are from TIFF header which is 12 bytes from the start
                //of the file
                $v = fseek($seek, $globalOffset + hexdec($value));
                $result['Errors'] = $result['Errors']++;
                $data = '';
            }
            $formated_data = $this->_formatData($type, $tag, $intel, $data);
            $result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
        }
    }
}