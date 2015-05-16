<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 * Date: 11.5.15 21:07
 */

namespace Vegan\MenuBundle\Component;


class ObjectManipulator
{

    /**
     * Method that return variables inside Object instance (public, private and protected!)
     *
     * @param object $objectInstance
     * @return array
     */
    public static function objectToArray ( &$objectInstance ) {
        $clone = (array) $objectInstance;
        $rtn = array ();
        $rtn['_SOURCE_KEYS_'] = $clone;

        while ( list ($key, $value) = each ($clone) ) {
            $aux = explode ("\0", $key);
            $newkey = $aux[count($aux)-1];
            $rtn[$newkey] = &$rtn['_SOURCE_KEYS_'][$key];
        }

        return $rtn;
    }

}
