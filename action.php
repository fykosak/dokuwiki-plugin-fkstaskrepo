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

    private $detFields = array('year','series','problem');
    private $modFields = array('name','origin','task');

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
        $controller->register_hook('HTML_SECEDIT_BUTTON','BEFORE',$this,'handle_html_secedit_button');
        $controller->register_hook('HTML_EDIT_FORMSELECTION','BEFORE',$this,'handle_html_edit_formselection');
        $controller->register_hook('ACTION_ACT_PREPROCESS','BEFORE',$this,'handle_action_act_preprocess');
        $controller->register_hook('PARSER_CACHE_USE','BEFORE',$this,'handle_parser_cache_use');
        $controller->register_hook('FETCH_MEDIA_STATUS','BEFORE',$this,'fetch_media_svg2png');
    }

    public function fetch_media_svg2png(Doku_Event &$event,$param) {
        global $conf;
        global $INPUT;
        if($event->data['ext'] != 'svg'){
            return;
        }
        if($event->data['width'] == 0 && $event->data['height'] == 0){
            return;
        }
        if(!$INPUT->has('topng')){
            return;
        }


        $xml = simplexml_load_file($event->data['file']);

        $w = $xml->attributes()->width;
        $h = $xml->attributes()->height;
        $v = $xml->attributes()->viewBox;

        if(!is_numeric($w) || !is_numeric($h)){
            preg_match('/([0-9]+)\s([0-9]+)\s([0-9]+)\s([0-9]+)/',$v,$m);
            $w = $m[3];
            $h = $m[4];
        }



        if(!$event->data['height']){
            $height = round(($event->data['width'] * $h) / $w);
        }else{
            $height = $event->data['height'];
        }


        $local = getCacheName($event->data['file'],'.media.'.$event->data['width'].'x'.$height.'.'.$event->data['ext'].'.png');
        $mtime = @filemtime($local);

        if($mtime < filemtime($event->data['file'])){
            $this->media_resize_imageIM($event->data['ext'],$event->data['file'],null,null,$local,$event->data['width'],$height);
        }
        if(!empty($conf['fperm'])){
            @chmod($local,$conf['fperm']);
        }

        sendFile($local,'image/png',$event->data['download'],$event->data['cache'],$event->data['ispublic'],$event->data['orig']);
    }

    private function media_resize_imageIM($ext,$from,$from_w,$from_h,$to,$to_w,$to_h) {
        global $conf;
        
        if(!$this->getConf('im_convert')) return false;

        $cmd = $this->getConf('im_convert');
        $cmd .= ' -resize '.$to_w.'x'.$to_h.'!';
        if($ext == 'jpg' || $ext == 'jpeg'){
            $cmd .= ' -quality '.$conf['jpg_quality'];
        }
        $cmd .= " $from $to";

        @exec($cmd,$out,$retval);


        if($retval == 0) return true;
        return false;
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_html_secedit_button(Doku_Event &$event,$param) {
        global $TEXT;
        if($event->data['target'] !== 'plugin_fkstaskrepo'){
            return;
        }
        //$event->data['name'] = $this->getLang('Edit'); // it's set in redner()
    }

    public function handle_html_edit_formselection(Doku_Event &$event,$param) {
        global $TEXT;
        global $INPUT;
        if($event->data['target'] !== 'plugin_fkstaskrepo'){
            return;
        }
        $event->preventDefault();

        unset($event->data['intro_locale']);

        // FIXME: Remove this if you want a media manager fallback link
        // You will probably want a media link if you want a normal toolbar
        $event->data['media_manager'] = false;

        echo $this->locale_xhtml('edit_intro');


        $form = $event->data['form'];

        if(array_key_exists('wikitext',$_POST)){
            foreach ($this->detFields as $field) {
                $parameters[$field] = $_POST[$field];
            }
        }else{
            $parameters = $this->helper->extractParameters($TEXT);
        }

        $data = $this->helper->getProblemData($parameters['year'],$parameters['series'],$parameters['problem'],$parameters['lang']);
        $data = array_merge($data,$parameters);

        $globAttr = array();
        if(!$event->data['wr']){
            $globAttr['readonly'] = 'readonly';
        }

        $form->startFieldset('Problem');
        // readonly fields
        foreach ($this->detFields as $field) {
            $attr = $globAttr;
            $attr['readonly'] = 'readonly';
            $value = $INPUT->post->str($field,$data[$field]);
            $form->addElement(form_makeTextField($field,$value,$this->getLang($field),$field,null,$attr));
        }

        // editable fields
        foreach ($this->modFields as $field) {
            $attr = $globAttr;
            if($field == 'task'){
                $value = $INPUT->post->str('wikitext',$data[$field]);
                $form->addElement(form_makeWikiText(cleanText($value),$attr));
            }else{
                $value = $INPUT->post->str($field,$data[$field]);
                $form->addElement(form_makeTextField($field,$value,$this->getLang($field),$field,null,$attr));
            }
        }

        $form->endFieldset();
    }

    public function handle_action_act_preprocess(Doku_Event &$event,$param) {

        global $INPUT;

        if($INPUT->str('target') != 'plugin_fkstaskrepo'){
            return;
        }
        if(!isset($_POST['do']['save'])){
            return;
        }
        global $TEXT;

        $TEXT = sprintf('<fkstaskrepo year="%s" series="%s" problem="%s" lang="%s"/>',$_POST['year'],$_POST['series'],$_POST['problem'],$_POST['lang']);

        $data = array();
        foreach ($this->modFields as $field) {
            if($field == 'task'){
                $data[$field] = cleanText($_POST['wikitext']);
            }else{
                $data[$field] = $_POST[$field];
            }
        }
        $this->helper->updateProblemData($data,$_POST['year'],$_POST['series'],$_POST['problem'],$_POST['lang']);
    }

    public function handle_parser_cache_use(Doku_Event &$event,$param) {
        $cache = & $event->data;

        // we're only interested in wiki pages
        if(!isset($cache->page)){
            return;
        }
        if($cache->mode != 'xhtml'){
            return;
        }

        // get meta data
        $depends = p_get_metadata($cache->page,'relation fkstaskrepo');
        if(!is_array($depends) || !count($depends)){
            return; // nothing to do
        }
        $cache->depends['files'] = !empty($cache->depends['files']) ? array_merge($cache->depends['files'],$depends) : $depends;
    }

}

