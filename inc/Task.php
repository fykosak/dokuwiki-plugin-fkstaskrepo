<?php

namespace FYKOS\dokuwiki\Extenstion\PluginTaskRepo;

use helper_plugin_fkstaskrepo;

/**
 * Class Task
 * @author Michal Koutný <michal@fykos.cz>
 * @author Michal Červeňák <miso@fykos.cz>
 * @author Štěpán Stenchlák <stenchlak@fykos.cz>
 * @property-read string $task
 */
class Task
{

    public static array $editableFields = [
        'name',
        'points',
        'origin',
        'task',
        'authors',
        'solution-authors',
        'figures',
    ];

    public static array $readonlyFields = [
        'year',
        'number',
        'series',
        'label',
        'lang',
    ];
    public int $number;
    public string $label;
    public string $name;
    public string $lang;
    public int $year;
    public int $series;
    public ?string $origin = null;
    public string $task;
    public ?int $points = null;
    public ?array $figures = null;
    public ?array $authors = null;
    public ?array $solutionAuthors = null;


    private TexPreproc $texPreproc;

    /**
     * name, origin, task, figures
     * @var array localized data stored in the file
     */
    private array $taskLocalizedData = [];

    private helper_plugin_fkstaskrepo $helper;

    public function __construct(helper_plugin_fkstaskrepo $helper, int $year, int $series, string $label, string $lang = 'cs')
    {
        $this->texPreproc = new TexPreproc();
        $this->year = $year;
        $this->series = $series;
        $this->label = strtoupper($label);
        $this->lang = $lang;
        $this->helper = $helper;
    }

    public function saveFiguresRawData(array $figures): void
    {
        $this->figures = [];

        foreach ($figures as $figure) {
            $figureGoodForm = [];
            $figureGoodForm['caption'] = $figure['caption']; // TODO never used!!!

            foreach ($figure as $ext => $data) {
                $name = $this->getAttachmentPath($figure['caption'], $ext);
                if (io_saveFile(mediaFN($name), (string)trim($data))) {
                    msg('Figure "' . $figure['caption'] . '" for language ' . $this->lang . ' has been saved', 1);
                } else {
                    msg('Figure "' . $figure['caption'] . '" for language ' . $this->lang . ' has not saved properly!', -1);
                }
                $this->figures[] = ['path' => $name, 'caption' => $figure['caption']];
            }
        }
    }

    /**
     * Returns ID path of the Attachment based on its caption
     * @param $caption string Attachment Caption
     * @param $type string File type
     * @return string ID
     */
    private function getAttachmentPath(string $caption, string $type): string
    {
        $name = substr(preg_replace("/[^a-zA-Z0-9_-]+/", '-', $caption), 0, 30) . '_' . substr(md5($caption . $type), 0, 5);
        return vsprintf($this->helper->getConf('attachment_path_' . $this->lang), [$this->year, $this->series, $this->label]) . ':' . $name . '.' . $type;
    }

    /**
     * Returns the path of .json file with task data.
     * @return string path of file
     */
    private function getFileName(): string
    {
        return MetaFN(vsprintf($this->helper->getConf('task_data_meta_path'), [$this->year, $this->series, $this->label]), null);
    }

    /**
     * Saves task
     */
    public function save(): void
    {
        $data = [
            'year' => $this->year,
            'series' => $this->series,
            'label' => $this->label,
            'number' => $this->number,
            'points' => $this->points,
            'authors' => $this->authors,
            'solution-authors' => $this->solutionAuthors,
            'localization' => $this->taskLocalizedData, // Includes old data
        ];

        $data['localization'][$this->lang] = [
            'name' => $this->name,
            'origin' => $this->origin,
            'task' => $this->task,
            'figures' => $this->figures,
        ];

        io_saveFile($this->getFileName(), json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Loads task
     * @return bool Success
     */
    public function load(): bool
    {
        $content = io_readFile($this->getFileName(), false);
        if (!$content) {
            return false;
        }
        $data = json_decode($content, true);

        $this->taskLocalizedData = $data['localization'];

        if (!key_exists($this->lang, $data['localization'])) {
            return false;
        }

        $this->number = $data['number'];
        $this->name = $data['localization'][$this->lang]['name'];
        $this->origin = $data['localization'][$this->lang]['origin'];
        $this->task = $data['localization'][$this->lang]['task'];
        $this->points = $data['points'];
        $this->figures = $data['localization'][$this->lang]['figures'];
        $this->authors = $data['authors'];
        $this->solutionAuthors = $data['solution-authors'];

        return true;
    }

    public function setTask(string $task, bool $preProc = true): void
    {
        if ($preProc) {
            $this->task = $this->texPreproc->preproc($task);
        } else {
            $this->task = $task;
        }
    }
}
