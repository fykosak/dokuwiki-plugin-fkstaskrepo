<?php
/**
 * DokuWiki Plugin fkstaskrepo (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_fkstaskrepo extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

       $controller->register_hook('HTML_SECEDIT_BUTTON', 'FIXME', $this, 'handle_html_secedit_button');
       $controller->register_hook('HTML_EDIT_FORMSELECTION', 'FIXME', $this, 'handle_html_edit_formselection');
       $controller->register_hook('ACTION_ACT_PREPROCESS', 'FIXME', $this, 'handle_action_act_preprocess');
   
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
    }

    public function handle_html_edit_formselection(Doku_Event &$event, $param) {
    }

    public function handle_action_act_preprocess(Doku_Event &$event, $param) {
    }

}

// vim:ts=4:sw=4:et:
