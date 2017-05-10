<?php

/**
 * DokuWiki Plugin fkstaskrepo (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class action_plugin_fkstaskrepo extends DokuWiki_Action_Plugin {

    private $detFields = ['year', 'series', 'problem', 'label'];
    private $modFields = ['name', 'origin', 'task'];
    private static $fields = [
        'year',
        'series',
        'problem',
        'label',
        'name',
        'origin',
        'tags',
        'task',
        'points',
        'figures',
        // 'authors',
        //'solution-authors'
    ];

    private static $tags = [
        'mechHmBodu',
        'mechTuhTel',
        'hydroMech',
        'mechPlynu',
        'gravPole',
        'kmitani',
        'vlneni',
        'molFyzika',
        'termoDyn',
        'statFyz',
        'optikaGeom',
        'optikaVln',
        'elProud',
        'elPole',
        'magPole',
        'relat',
        'kvantFyz',
        'jadFyz',
        'astroFyz',
        'matematika',
        'chemie',
        'biofyzika',
        'other'
    ];

    /**
     * @var helper_plugin_fkstaskrepo
     */
    private $helper;

    public function __construct() {
        $this->helper = $this->loadHelper('fkstaskrepo');
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'tplEditForm');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'editTask');

        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handle_parser_cache_use');
        $controller->register_hook('FETCH_MEDIA_STATUS', 'BEFORE', $this, 'fetch_media_svg2png');
    }

    public function fetch_media_svg2png(Doku_Event &$event, $param) {
        global $conf;
        global $INPUT;
        if ($event->data['ext'] != 'svg') {
            return;
        }
        if ($event->data['width'] == 0 && $event->data['height'] == 0) {
            return;
        }
        if (!$INPUT->has('topng')) {
            return;
        }

        $xml = simplexml_load_file($event->data['file']);

        $w = $xml->attributes()->width;
        $h = $xml->attributes()->height;
        $v = $xml->attributes()->viewBox;

        if (!is_numeric($w) || !is_numeric($h)) {
            preg_match('/([0-9]+)\s([0-9]+)\s([0-9]+)\s([0-9]+)/', $v, $m);
            $w = $m[3];
            $h = $m[4];
        }
        if (!$event->data['height']) {
            $height = round(($event->data['width'] * $h) / $w);
        } else {
            $height = $event->data['height'];
        }
        $local = getCacheName($event->data['file'], '.media.' . $event->data['width'] . 'x' . $height . '.' . $event->data['ext'] . '.png');
        $mtime = @filemtime($local);

        if ($mtime < filemtime($event->data['file'])) {
            $this->media_resize_imageIM($event->data['ext'], $event->data['file'], null, null, $local, $event->data['width'], $height);
        }
        if (!empty($conf['fperm'])) {
            @chmod($local, $conf['fperm']);
        }

        sendFile($local, 'image/png', $event->data['download'], $event->data['cache'], $event->data['ispublic'], $event->data['orig']);
    }

    private function media_resize_imageIM($ext, $from, $from_w, $from_h, $to, $to_w, $to_h) {
        global $conf;
        if (!$this->getConf('im_convert')) return false;

        $cmd = $this->getConf('im_convert');
        $cmd .= ' -resize ' . $to_w . 'x' . $to_h . '!';
        if ($ext == 'jpg' || $ext == 'jpeg') {
            $cmd .= ' -quality ' . $conf['jpg_quality'];
        }
        $cmd .= " $from $to";
        @exec($cmd, $out, $retval);
        if ($retval == 0) return true;
        return false;
    }


    public function tplEditForm(Doku_Event &$event, $param) {
        global $INPUT;
        if ($event->data !== 'plugin_fkstaskrepo') {
            return;
        }
        $event->preventDefault();
        echo '<h1>Edit task</h1>';

        $form = new \dokuwiki\Form\Form();
        $form->setHiddenField('task[do]', 'update');

        $data = $this->helper->getProblemData($INPUT->param('task')['year'], $INPUT->param('task')['series'], $INPUT->param('task')['problem'], $INPUT->param('task')['lang']);
        var_dump($data);
        /*    'authors' =>
    array (size=1)
      'author' => string 'hanzelka' (length=8)
  'solution-authors' =>
    array (size=1)
      'solution-author' => string 'smitalova' (length=9)
*/
        foreach ($data as $field => $value) {
            $form->addTagOpen('div')
                ->addClass('form-group');
            switch ($field) {
                case 'task':
                    $form->addTextarea('problem[task]', $this->getLang($field))
                        ->attrs(['class' => 'form-control'])
                        ->val($value);
                    break;
                case 'year':
                case 'number':
                case 'series':
                case 'problem':
                case 'label':
                case 'points':
                case 'lang':
                    $form->setHiddenField('problem[' . $field . ']', $value);
                    $form->addTagOpen('label')
                        ->addClass('form-group');//->//form-control-static
                    $form->addHTML('<label><span>' . $this->getLang($field) . '</span><p class="form-control-static">' . $value . '</p></label>');
                    break;
                case 'figures':
                    $form->addFieldsetOpen('figures');
                    foreach ($data['figures'] as $key => $figure) {
                        $form->addTextInput('problem[figures][' . $key . '][path]', 'figures_path')
                            ->val($figure['path']);
                        $form->addTextInput('problem[figures][' . $key . '][caption]', 'figure_caption')
                            ->val($figure['caption']);
                    }
                    $form->addFieldsetClose();
                    break;
                case 'topics':
                    $this->getTagsField($form, $data);
                    break;
                case 'name':
                case 'origin':
                    $form->addTextInput('problem[' . $field . ']', $this->getLang($field))
                        ->attrs(['class' => 'form-control'])
                        ->val($value);
                    break;
                case 'authors':
                case 'solution-authors':
                    if (is_array($value)) {
                        foreach ($value as $subField => $subValue) {
                            if (is_array($subValue)) {
                                $fieldValue = implode(',',$subValue);
                            } else {
                                $fieldValue = $subValue;
                            }
                            $form->addTextInput('problem[' . $field . '][' . $subField . ']', $this->getLang($field))
                                ->attrs(['class' => 'form-control'])
                                ->val($fieldValue);
                        }
                    }
                    break;
                default:
                    var_dump($field);


            }
            $form->addTagClose('div');
        }

        $form->addButton('submit', 'Uložiť');
        echo $form->toHTML();
    }

    private function getTagsField(\dokuwiki\Form\Form $form, $data) {
        $form->addFieldsetOpen('tags');
        foreach (self::$tags as $tag) {
            $form->addTagOpen('div')
                ->addClass('form-check');
            if (is_array($data['topics']['topic'])) {
                $isIn = in_array($tag, $data['topics']['topic']);

            } else {
                $isIn = $data['tags'] == $data['topics']['topic'];
            }
            $input = $form->addCheckbox('problem[topics][topic][]', $this->getLang('tag__' . $tag))
                ->val($tag);
            if ($isIn) {
                $input->attr('checked', 'checked');
            }
            $form->addTagClose('div');
        }
        $form->addFieldsetClose();
    }

    public function editTask(Doku_Event &$event) {
        global $INPUT;
        if ($event->data !== 'plugin_fkstaskrepo') {
            return;
        }

        $event->preventDefault();
        $event->stopPropagation();
        switch ($INPUT->param('task')['do']) {
            case 'update':
                $this->updateProblem();
                break;
            case 'edit':
                break;
        }
    }

    private function updateProblem() {
        global $INPUT;
        $data = [];
        $problemData = $INPUT->param('problem');
        $problemData['task'] = cleanText($INPUT->param('problem')['task']);
        die();
        $this->helper->updateProblemData($data, $problemData['year'], $problemData['series'], $problemData['problem'], $problemData['lang']);
    }

    public function handle_parser_cache_use(Doku_Event &$event, $param) {
        $cache = &$event->data;

        // we're only interested in wiki pages
        if (!isset($cache->page)) {
            return;
        }
        if ($cache->mode != 'xhtml') {
            return;
        }

        // get meta data
        $depends = p_get_metadata($cache->page, 'relation fkstaskrepo');
        if (!is_array($depends) || !count($depends)) {
            return; // nothing to do
        }
        $cache->depends['files'] = !empty($cache->depends['files']) ? array_merge($cache->depends['files'], $depends) : $depends;
    }

}

