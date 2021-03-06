<?php

namespace FYKOS\dokuwiki\Extenstion\PluginTaskRepo;

use helper_plugin_fkstaskrepo;

/**
 * Class AbstractRenderer
 * @author Michal Koutný <michal@fykos.cz>
 * @author Michal Červeňák <miso@fykos.cz>
 * @author Štěpán Stenchlák <stenchlak@fykos.cz>
 */
abstract class AbstractRenderer {

    protected const SUPPORTED_IMAGES = [
        'gif',
        'jpg',
        'jpeg',
        'png',
        'svg',
        'ico',
    ];

    protected $helper;

    public function __construct(helper_plugin_fkstaskrepo $helper) {
        $this->helper = $helper;
    }

    abstract public function render(\Doku_Renderer $renderer, Task $data, bool $full = false): void;
}
