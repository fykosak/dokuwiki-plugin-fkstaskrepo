<?php

namespace FYKOS\dokuwiki\Extenstion\PluginTaskRepo;
/**
 * @property array tags
 * @property array topics
 */
class FSSUTask extends Task
{
    public static function getEditableFields(): array
    {
        return [...parent::getEditableFields(), 'tags', 'topics'];
    }
}
