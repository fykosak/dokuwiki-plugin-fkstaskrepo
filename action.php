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
        'other',
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
    }

    public function tplEditForm(Doku_Event &$event) {
        global $INPUT;
        if ($event->data !== 'plugin_fkstaskrepo') {
            return;
        }
        $event->preventDefault();
        echo '<h1>' . $this->getLang('edit_form_title') . '</h1>';

        $problem = new \PluginFKSTaskRepo\Task(
            $INPUT->param('task')['year'],
            $INPUT->param('task')['series'],
            $INPUT->param('task')['problem'],
            $INPUT->param('task')['lang']
        );
        $problem->load();

        $form = new \dokuwiki\Form\Form();
        $form->addClass('task-repo-edit');
        $form->setHiddenField('task[do]', 'update');
        $form->setHiddenField('do', 'plugin_fkstaskrepo');

        foreach (\PluginFKSTaskRepo\Task::$readonlyFields as $field) {
            $form->addTagOpen('div')->addClass('form-group');
            switch ($field) {
                case 'year':
                    $this->addStaticField($form, $field, $problem->getYear());
                    break;
                case 'number':
                    $this->addStaticField($form, $field, $problem->getNumber());
                    break;
                case 'series':
                    $this->addStaticField($form, $field, $problem->getSeries());
                    break;
                case 'label':
                    $this->addStaticField($form, $field, $problem->getLabel());
                    break;
                case 'lang':
                    $this->addStaticField($form, $field, $problem->getlang());
                    break;
            }
            $form->addTagClose('div');
        }
        foreach (\PluginFKSTaskRepo\Task::$editableFields as $field) {
            $form->addTagOpen('div')->addClass('form-group');
            switch ($field) {
                case 'task':
                    $form->addTextarea('problem[task]', $this->getLang($field))->attrs(['class' => 'form-control', 'rows' => 5])
                        ->val($problem->getTask());
                    break;
                case 'figures':
                    $form->addFieldsetOpen($this->getLang('figures'));
                    $form->addTag('div')->addClass('figures')->attr('data-value', json_encode($problem->getFigures()));
                    $form->addFieldsetClose();
                    break;
                case 'name':
                    $form->addTextInput('problem[name]', $this->getLang($field))
                        ->attrs(['class' => 'form-control'])->val($problem->getName());
                    $form->addHTML('<small class="form-text">' . $this->getLang('edit_form_name_warning') . '</small>');
                    break;
                case 'origin':
                    $form->addTextInput('problem[origin]', $this->getLang($field))
                        ->attrs(['class' => 'form-control'])->val($problem->getOrigin());
                    break;
                case 'authors':
                    $value = implode(',', $problem->getAuthors());
                    $form->addTextInput('problem[authors]', $this->getLang($field))
                        ->attrs(['class' => 'form-control'])->val($value);
                    break;

                case 'solution-authors':
                    $value = implode(',', $problem->getSolutionAuthors());
                    $form->addTextInput('problem[solution-authors]', $this->getLang($field))
                        ->attrs(['class' => 'form-control'])->val($value);
                    break;
                case 'points':
                    $inputElement = new dokuwiki\Form\InputElement('number', 'problem[points]', $this->getLang($field));
                    $inputElement->val($problem->getPoints());
                    $inputElement->attrs(['class' => 'form-control']);
                    $form->addElement($inputElement);
                    $form->addHTML('<small class="form-text">' . $this->getLang('edit_form_points_warning') . '</small>');
                    break;
            }
            $form->addTagClose('div');
        }
        $this->addTagsField($form, $problem);
        $form->addHTML('<hr>');
        $form->addHTML('<p>' . $this->getLang('edit_form_submit_warning') . '</p>');
        $form->addButton('submit', $this->getLang('save'))->addClass('btn btn-primary');
        echo $form->toHTML();
    }

    private function addStaticField(\dokuwiki\Form\Form &$form, $field, $value) {
        $form->addTextInput('problem[' . $field . ']', $this->getLang($field))
            ->attrs(['class' => 'form-control', 'readonly' => 'readonly'])->val($value);
    }

    private function addTagsField(\dokuwiki\Form\Form $form, \PluginFKSTaskRepo\Task $data) {
        $form->addFieldsetOpen($this->getLang('tags'));

        $form->addTagOpen('div')->addClass('row');
        $topics = $this->helper->loadTags($data->getYear(), $data->getSeries(), $data->getLabel());
        foreach (self::$tags as $tag) {
            $form->addTagOpen('div')->addClass('form-check col-lg-4 col-md-6 col-sm-12');
            $isIn = false;
            if (is_array($topics)) {
                $isIn = in_array($tag, $topics);
            }
            $input = $form->addCheckbox('problem[topics][]', $this->getLang('tag__' . $tag))->val($tag);
            if ($isIn) {
                $input->attr('checked', 'checked');
            }
            $form->addTagClose('div');
        }
        $form->addTagClose('div');
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
                $this->updateProblem($event);
                break;
            case 'edit':
                break;
        }
    }

    private function updateProblem(Doku_Event &$event) {
        global $INPUT;

        $problemData = $INPUT->param('problem');

        $problem = new \PluginFKSTaskRepo\Task($problemData['year'], $problemData['series'], $problemData['label'], $problemData['lang']);
        $problem->setTask(cleanText($INPUT->param('problem')['task']));
        $problem->setOrigin($INPUT->param('problem')['origin']);
        $problem->setNumber((int)$INPUT->param('problem')['number']);
        $problem->setName($INPUT->param('problem')['name']);
        $problem->setPoints((int)$INPUT->param('problem')['points']);
        $problem->setFigures($INPUT->param('problem')['figures']);
        $problem->setSolutionAuthors(explode(',', $INPUT->param('problem')['solution-authors']));
        $problem->setAuthors(explode(',', $INPUT->param('problem')['authors']));
        $problem->save();
        $this->helper->storeTags($problem->getYear(), $problem->getSeries(), $problem->getLabel(), $INPUT->param('problem')['topics']);
        $event->data = 'show';
    }

    public function handle_parser_cache_use(Doku_Event &$event) {
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
        $cache->depends['files'] = !empty($cache->depends['files']) ? array_merge($cache->depends['files'],
            $depends) : $depends;
    }
}

