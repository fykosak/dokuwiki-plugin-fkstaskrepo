<?php

/**
 * DokuWiki Plugin fkstaskrepo (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

class helper_plugin_fkstaskrepo extends DokuWiki_Plugin {

    /**
     * Return info about supported methods in this Helper Plugin
     *
     * @return array of public methods
     */
    public function getMethods() {
        return array(
                //TODO
        );
    }

    public function getPath($year, $series) {
        $mask = $this->getConf('path_mask');
        return sprintf($mask, $year, $series);
    }

}

// vim:ts=4:sw=4:et:
