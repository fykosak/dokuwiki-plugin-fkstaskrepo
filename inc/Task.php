<?php

namespace FYKOS\dokuwiki\Extenstion\PluginTaskRepo;

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

    /**
     * name, origin, task, figures
     * @var array localized data stored in the file
     */
    public array $taskLocalizedData = [];

    public function __construct(int $year, int $series, string $label, string $lang = 'cs')
    {
        $this->year = $year;
        $this->series = $series;
        $this->label = strtoupper($label);
        $this->lang = $lang;
    }

    public function setTask(string $task, bool $preProc = true): void
    {
        if ($preProc) {
            $this->task = (new TexPreproc())->preproc($task);
        } else {
            $this->task = $task;
        }
    }
}
