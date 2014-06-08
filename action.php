<?php

/**
 * DokuWiki Plugin fkstaskrepo (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

class action_plugin_fkstaskrepo extends DokuWiki_Action_Plugin {

    private $detFields = array('year', 'series', 'problem');
    private $modFields = array('name', 'origin', 'task');

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

        $controller->register_hook('HTML_SECEDIT_BUTTON', 'BEFORE', $this, 'handle_html_secedit_button');
        $controller->register_hook('HTML_EDIT_FORMSELECTION', 'BEFORE', $this, 'handle_html_edit_formselection');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_action_act_preprocess');
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_html_secedit_button(Doku_Event &$event, $param) {
        global $TEXT;
        if ($event->data['target'] !== 'plugin_fkstaskrepo') {
            return;
        }
        //$event->data['name'] = $this->getLang('Edit'); // it's set in redner()
    }

    public function handle_html_edit_formselection(Doku_Event &$event, $param) {
        global $TEXT;
        if ($event->data['target'] !== 'plugin_fkstaskrepo') {
            return;
        }
        $event->preventDefault();

        unset($event->data['intro_locale']);

        // FIXME: Remove this if you want a media manager fallback link
        // You will probably want a media link if you want a normal toolbar
        $event->data['media_manager'] = false;

        echo $this->locale_xhtml('edit_intro');


        $form = $event->data['form'];

        $parameters = syntax_plugin_fkstaskrepo::extractParameters($TEXT, $this);

        // TODO load problem data from metadata

        $globAttr = array();
        if (!$event->data['wr']) {
            $globAttr['readonly'] = 'readonly';
        }

        $form->startFieldset('Problem');
        // readonly fields
        foreach ($this->detFields as $field) {
            $attr = $globAttr;
            $attr['readonly'] = 'readonly';
            $form->addElement(form_makeTextField($field, $parameters[$field], $this->getLang($field), $field, null, $attr));
        }

        // editable fields
        foreach ($this->modFields as $field) {
            $attr = $globAttr;
            if ($field == 'task') {
                $form->addElement(form_makeWikiText('', $attr));
            } else {
                $form->addElement(form_makeTextField($field, $parameters[$field], $this->getLang($field), $field, null, $attr));
            }
        }

        $form->endFieldset();
    }

    public function handle_action_act_preprocess(Doku_Event &$event, $param) {
        if (!isset($_POST[reset($this->detFields)])) {
            return;
        }
        global $TEXT;

        $TEXT = sprintf('<fkstaskrepo year="%s" series="%s" problem="%s"/>', $_POST['year'], $_POST['series'], $_POST['problem']);

        //TODO store to metadata
        // TODO and invalidate original page (source doesn't change) or put dependency on metadata
    }

}

// vim:ts=4:sw=4:et:
