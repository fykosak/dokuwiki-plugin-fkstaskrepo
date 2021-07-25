<?php

namespace FYKOS\dokuwiki\Extenstion\PluginTaskRepo;

use helper_plugin_fkstaskrepo;

/**
 * Class AbstractRenderer
 * @author Michal Koutný <michal@fykos.cz>
 * @author Michal Červeňák <miso@fykos.cz>
 * @author Štěpán Stenchlák <stenchlak@fykos.cz>
 */
abstract class AbstractRenderer
{

    protected const SUPPORTED_IMAGES = [
        'gif',
        'jpg',
        'jpeg',
        'png',
        'svg',
        'ico',
    ];

    protected helper_plugin_fkstaskrepo $helper;

    public function __construct(helper_plugin_fkstaskrepo $helper)
    {
        $this->helper = $helper;
    }

    abstract public function render(\Doku_Renderer $renderer, Task $task, bool $full = false): void;

    /**
     * Decides whether the file is a picture.
     * @param string $file
     * @return bool is image
     */
    protected function isImage(string $file): bool
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)); // Using strtolower to overcome case sensitive
        return in_array($ext, self::SUPPORTED_IMAGES);
    }
}
