<?php
/**
 * Created by IntelliJ IDEA.
 * User: miso
 * Date: 23.8.2017
 * Time: 16:09
 */

namespace PluginFKSTaskRepo;


class Task {

    public static $editableFields = [
        'name',
        'origin',
        'task',
        'figures',
        'authors',
        'solution-authors',
    ];

    public static $readonlyFields = [
        'year',
        'points',
        'number',
        'series',
        'label',
        'lang',
    ];
    private $number;
    private $label;
    private $name;
    private $lang;
    private $origin;
    private $task;
    private $points;
    private $figures;
    private $authors;
    private $solutionAuthors;
    private $year;
    /**
     * @var \PluginFKSTaskRepo\TexPreproc;
     */

    private $texPreproc;

    /**
     * @return mixed
     */
    public function getSolutionAuthors() {
        return $this->solutionAuthors ?: [];
    }

    /**
     * @param mixed $solutionAuthors
     */
    public function setSolutionAuthors($solutionAuthors) {
        $this->solutionAuthors = $solutionAuthors;
    }

    /**
     * @return mixed
     */
    public function getYear() {
        return $this->year;
    }

    /**
     * @return mixed
     */
    public function getSeries() {
        return $this->series;
    }

    private $series;

    public function __construct($year, $series, $label, $lang = 'cs') {
        $this->texPreproc = new TexPreproc();
        $this->year = $year;
        $this->series = $series;
        $this->label = $label;
        $this->lang = $lang;
    }

    public function isActualLang(\SimpleXMLElement $e) {
        return (($this->lang == (string)$e->attributes(\helper_plugin_fkstaskrepo::XMLNamespace)->lang) ||
            (string)$e->attributes(\helper_plugin_fkstaskrepo::XMLNamespace)->lang == "");
    }

    /**
     * @param \SimpleXMLElement $problem
     */
    public function extractFigure(\SimpleXMLElement $problem) {
        if ((string)$problem->figures != "") {
            foreach ($problem->figures->figure as $figure) {
                if ($this->isActualLang($figure)) {
                    $simpleFigure = [];
                    $simpleFigure['caption'] = (string)$figure->caption;
                    /**
                     * @var $data \SimpleXMLElement
                     */
                    foreach ($figure->data as $data) {
                        $type = (string)$data->attributes()->extension;
                        $simpleFigure['data'][$type] = trim((string)$data);
                    }
                    $this->saveFigures($simpleFigure);
                }
            }
        }
    }

    /**
     * @param $figure []
     */
    private function saveFigures($figure) {
        if (!empty($figure) && !empty($figure['data'])) {
            foreach ($figure['data'] as $type => $imgContent) {
                if (!$type) {
                    msg('invalid or empty extenson figure: ' . $type, -1);
                    continue;
                }
                $name = $this->getImagePath($type);
                if (io_saveFile(mediaFN($name), (string)trim($imgContent))) {
                    msg('image: ' . $name . ' for language ' . $this->lang . ' has been saved', 1);
                }
                $this->figures[] = ['path' => $name, 'caption' => $figure['caption']];
            }
        }
    }

    /**
     * @param string $type
     * @return string
     */
    private function getImagePath($type = null) {
        if ($type) {
            return $this->getPluginName() . ':figure:year' . $this->year . '_series' . $this->series . '_' . $this->label . '_' .
                $this->lang . '.' . $type;
        }
        return $this->getPluginName() . ':figure:year' . $this->year . '_series' . $this->series . '_' . $this->label . '_' . $this->lang;
    }

    public function getFileName() {
        $id = 'fkstaskrepo:' . $this->year . ':' . $this->series . '-' . $this->label . '_' . $this->lang;
        return metaFN($id, '.dat');
    }

    public function save() {
        $data = [
            'year' => $this->year,
            'series' => $this->series,
            'label' => $this->label,
            'lang' => $this->lang,
            'number' => $this->number,
            'name' => $this->name,
            'origin' => $this->origin,
            'task' => $this->task,
            'points' => $this->points,
            'figures' => $this->figures,
            'authors' => $this->authors,
            'solution-authors' => $this->solutionAuthors,
        ];
        io_saveFile($this->getFileName(), serialize($data));
    }

    public function load() {
        $content = io_readFile($this->getFileName(), false);
        if (!$content) {
            return;
        }
        $data = unserialize($content);

        $this->number = $data['number'];
        $this->name = $data['name'];
        $this->origin = $data['origin'];
        $this->task = $data['task'];
        $this->points = $data['points'];
        $this->figures = $data['figures'];
        $this->authors = $data['authors'];
        $this->solutionAuthors = $data['solution-authors'];
    }

    public function getPluginName() {
        return 'fkstaskrepo';
    }

    /**
     * @return mixed
     */
    public function getNumber() {
        return $this->number;
    }

    /**
     * @param mixed $number
     */
    public function setNumber($number) {
        $this->number = $number;
    }

    /**
     * @return mixed
     */
    public function getLabel() {
        return $this->label;
    }

    /**
     * @param mixed $label
     */
    public function setLabel($label) {
        $this->label = $label;
    }

    /**
     * @return mixed
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getLang() {
        return $this->lang;
    }

    /**
     * @param mixed $lang
     */
    public function setLang($lang) {
        $this->lang = $lang;
    }

    /**
     * @return mixed
     */
    public function getOrigin() {
        return $this->origin;
    }

    /**
     * @param mixed $origin
     */
    public function setOrigin($origin) {
        $this->origin = $origin;
    }

    /**
     * @return mixed
     */
    public function getTask() {
        return $this->task;
    }

    /**
     * @param mixed $task
     */
    public function setTask($task) {
        $this->task = $this->texPreproc->preproc($task);
    }

    /**
     * @return mixed
     */
    public function getPoints() {
        return $this->points;
    }

    /**
     * @param mixed $points
     */
    public function setPoints($points) {
        $this->points = $points;
    }

    /**
     * @return mixed
     */
    public function getFigures() {
        return $this->figures ?: [];
    }

    /**
     * @param mixed $figures
     */
    public function setFigures($figures) {
        $this->figures = $figures;
    }

    /**
     * @return mixed
     */
    public function getAuthors() {
        return $this->authors ?: [];
    }

    /**
     * @param mixed $authors
     */
    public function setAuthors($authors) {
        $this->authors = $authors;
    }


}
