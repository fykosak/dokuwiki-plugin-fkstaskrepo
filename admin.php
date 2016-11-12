<?php

/**
 * DokuWiki Plugin fkstaskrepo (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michal Koutný <michal@fykos.cz>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class admin_plugin_fkstaskrepo extends DokuWiki_Admin_Plugin {

    const DEFAULT_LANGUAGE = 'cs';

    /**
     *
     * @var helper_plugin_fkstaskrepo
     */
    private $helper;

    public function __construct() {
        $this->helper = $this->loadHelper('fkstaskrepo');
    }

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort() {
        return 10;
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly() {
        return false;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle() {

    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html() {
        global $ID;
        global $INPUT;

        $year = $INPUT->post->int('year', null);
        $series = $INPUT->post->int('series', null);
        $language = $INPUT->post->str('language', null);

        ptln('<h1>' . $this->getLang('menu') . '</h1>');
        $form = new \dokuwiki\Form\Form();

        $form->attrs(['class' => $this->getPluginName(), 'enctype' => 'multipart/form-data']);
        $form->addFieldsetOpen($this->getLang('update'));
        $form->setHiddenField('id', $ID);
        $form->setHiddenField('do', 'admin');
        $form->addTextInput('year', $this->getLang('year'))->attr('pattern', '[0-9]+');
        $form->addTextInput('series', $this->getLang('series'))->attr('pattern', '[0-9]+');
        $form->addDropdown('language',array('ALL', 'cs', 'en'),$this->getLang('language'));

       $form->addElement(new \dokuwiki\Form\InputElement('file','xml_file',$this->getLang('xml_file')));
$form->addCheckbox('hard',$this->getLang('hard_update'));
        $form->addButton('submit', $this->getLang('update'));
        //$form->addElement(form_makeFileField('xml_file', '<span title="' . $this->getLang('xml_source_help') . '">' . $this->getLang('xml_file') . '</span>'));
       // $form->addElement(form_makeCheckboxField('hard', '1', $this->getLang('hard_update')));
       // $form->addElement(form_makeButton('submit', 'admin', $this->getLang('update')));
       // $form->endFieldset();
       // $form->printForm();
        $form->addFieldsetClose();
        echo $form->toHTML();

        if ($year !== null && $series !== null && $language !== null) {
            if ($language == "ALL") {
                $language = null;
            }
            if ($_FILES['xml_file'] && $_FILES['xml_file']['name']) {
                if ($_FILES['xml_file']['error'] > 0) {
                    msg('Upload failed.', -1);
                    return;
                }
                $dst = $this->helper->getSeriesFilename($year, $series);
                move_uploaded_file($_FILES['xml_file']['tmp_name'], $dst);
                $content = file_get_contents($dst);
            } else {
                $content = $this->helper->getSeriesData($year, $series, helper_plugin_fksdownloader::EXPIRATION_FRESH);
                if (!$content) {
                    return;
                }
            }
            $this->processSeries($content, $year, $series, $language);
        }
    }

    private function processSeries($content, $year, $series, $language) {
        global $INPUT;

        // series template
        $seriesXML = simplexml_load_string($content);
        $deadline = $seriesXML->deadline;
        $dedline_post = $seriesXML->{'deadline-post'};


        if ($INPUT->int('hard') == 0) {

            if ((int)$seriesXML->number != $series) {
                msg('Series must be same as in the XML', -1);
                return;
            }
            if ((int)$seriesXML->year != $year) {
                msg('Year must be same as in the XML', -1);
                return;
            }
        }

        $langs = $this->getLanguages($seriesXML);

        foreach ($langs as $lang) {

            if ($language && $lang != $language) {
                continue;
            }

            $parameters = array('figure' => '@figure@', 'human-year' => $year . '. ' . $this->helper->getSpecLang('years', $lang), 'human-series' => $series . '. ' . $this->helper->getSpecLang('series', $lang), 'label' => '@label@', 'human-deadline' => $this->helper->getSpecLang('deadline', $lang) . ': ' . date($this->helper->getSpecLang('deadline-format', $lang), strtotime($deadline)), 'human-deadline-post' => $this->helper->getSpecLang('deadline-post', $lang) . ': ' . date($this->helper->getSpecLang('deadline-post-format', $lang), strtotime($dedline_post)));

            $pagePath = sprintf($this->getConf('page_path_mask_' . $lang), $year, $series);

            if ($pagePath == "") {
                msg('No page path defined for language ' . $lang, -1);
                continue;
            }
            $pageTemplate = io_readFile(wikiFN($this->getConf('series_template')));


            $parameters['lang'] = $lang;
            $parameters ['year'] = $year;
            $parameters['series'] = $series;
            $pageContent = $this->replaceVariables($parameters, $pageTemplate);


            $that = $this;
            $pageContent = preg_replace_callback('/--\s*problem\s--(.*)--\s*endproblem\s*--/is', function ($match) use ($seriesXML, $that, $parameters, $year, $series, $lang) {
                $result = '';
                $problemTemplate = $match[1];


                foreach ($seriesXML->problems->children() as $problem) {
                    $parameters['figure'] = "";
                    $parameters['label'] = (string)$problem->label;
                    $d = $this->helper->extractFigure($problem, $lang);
                    if (!empty($d) && !empty($d['data'])) {
                        foreach ($d['data'] as $type => $imgContent) {
                            if (!$type) {
                                msg('invalid or empty extenson figure: ' . $type, -1);
                                continue;
                            }
                            $name = $that->helper->getImagePath($year, $series, (string)$problem->label, $lang, $type);
                            $parameters['figure'] = '{{probfig>' . $this->helper->getImagePath($year, $series, (string)$problem->label, $lang) . ' |' . $d['caption'] . '}}';

                            if (io_saveFile(mediaFN($name), (string)trim($imgContent))) {
                                msg('image: ' . $name . ' for language ' . $lang . ' has been saved', 1);
                            }
                        }
                    }
                    foreach ($problem->task as $task) {

                        if ($this->helper->isActualLang($task, $lang)) {
                            $text = $this->helper->texPreproc->preproc((string)$task);
                            if ($text) {
                                $this->helper->updateProblemData(array('task' => $text), $year, $series, $problem->label, $lang);
                            }
                            break;
                        }
                    }
                    $this->helper->storeTags($year, $series, $problem->label, $problem->topics->topic);
                    $result .= $that->replaceVariables($parameters, $problemTemplate);
                }
                return $result;
            }, $pageContent);


            io_saveFile(wikiFN($pagePath), $pageContent);

            msg(sprintf('Updated <a href="%s">%s</a>.', wl($pagePath), $pagePath));
        }
    }


    private function getLanguages(SimpleXMLElement $seriesXML) {
        $langs = array();
        foreach ($seriesXML->xpath('problems/problem/*[@xml:lang]') as $e) {
            $l = (string)$e->attributes('http://www.w3.org/XML/1998/namespace')->lang;
            if (!in_array($l, $langs)) {
                $langs[] = $l;
            }
        }
        return $langs;
    }

    private function replaceVariables($parameters, $template) {
        $that = $this;

        $result = preg_replace_callback('/@([^@]+)@/', function ($match) use ($parameters, $that) {
            $key = $match[1];
            if (!isset($parameters[$key])) {
                msg(sprintf($that->getLang('undefined_template_variable'), $key));
                return '';
            } else {
                return $parameters[$key];
            }
        }, $template);
        return $result;
    }


}

// vim:ts=4:sw=4:et: