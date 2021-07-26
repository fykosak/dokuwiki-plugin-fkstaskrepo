<?php

namespace FYKOS\dokuwiki\Extenstion\PluginTaskRepo;

/**
 * Class Task
 * @author Michal Koutný <michal@fykos.cz>
 * @author Michal Červeňák <miso@fykos.cz>
 * @author Štěpán Stenchlák <stenchlak@fykos.cz>
 * @property int $number
 * @property string $label
 * @property string $name
 * @property string $lang
 * @property int $year
 * @property int $series
 * @property string|null $origin
 * @property string $task
 * @property int|null $points
 * @property array|null $figures
 * @property array|null $authors
 * @property array|null $solutionAuthors
 */
class Task
{
    private array $data = [];


    /**
     * name, origin, task, figures
     * @var array localized data stored in the file
     */
    public array $taskLocalizedData = [];

    public function __construct(int $year, int $series, string $label, string $lang = 'cs')
    {
        $this->data['year'] = $year;
        $this->data['series'] = $series;
        $this->data['label'] = strtoupper($label);
        $this->data['lang'] = $lang;
    }

    public function __get(string $name)
    {
        if (in_array($name, [...static::getEditableFields(), ...static::getReadonlyFields()])) {
            return $this->data[$name] ?? null;
        }
        throw new \InvalidArgumentException(sprintf('Property %s does not exists', $name));
    }

    public function __set(string $name, $value): void
    {
        if (in_array($name, [...static::getEditableFields()])) {
            $this->data[$name] = $value;
            return;
        }
        throw new \InvalidArgumentException();
    }

    public static function getEditableFields(): array
    {
        return [
            'name',
            'points',
            'origin',
            'task',
            'authors',
            'solution-authors',
            'figures',
        ];
    }

    public static function getReadonlyFields(): array
    {
        return [
            'year',
            'number',
            'series',
            'label',
            'lang',
        ];
    }
}
