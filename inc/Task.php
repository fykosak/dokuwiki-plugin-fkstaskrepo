<?php

namespace FYKOS\dokuwiki\Extenstion\PluginTaskRepo;

/**
 * Class Task
 * @author Michal Koutný <michal@fykos.cz>
 * @author Michal Červeňák <miso@fykos.cz>
 * @author Štěpán Stenchlák <stenchlak@fykos.cz>
 * @property-read string $label
 * @property-read string $lang
 * @property-read int $year
 * @property-read int $series
 */
class Task
{
    public int $number;
    public string $name;
    public ?string $origin;
    public string $task;
    public ?int $points;
    public ?array $figures;
    public ?array $authors;
    public ?array $solutionAuthors;

    private string $label;
    private string $lang;
    private int $year;
    private int $series;

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

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        if (in_array($name, static::getReadonlyFields())) {
            return $this->$name;
        }
        throw new \InvalidArgumentException(sprintf('Property %s does not exists', $name));
    }

    public static function getEditableFields(): array
    {
        return [
            'number',
            'name',
            'points',
            'origin',
            'task',
            'authors',
            'solutionAuthors',
            'figures',
        ];
    }

    public static function getReadonlyFields(): array
    {
        return [
            'year',
            'series',
            'label',
            'lang',
        ];
    }
}
