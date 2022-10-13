<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\Form\Form;
use FYKOS\dokuwiki\Extenstion\PluginTaskRepo\FSSUTask;
use FYKOS\dokuwiki\Extenstion\PluginTaskRepo\Task;

abstract class AbstractProblem extends SyntaxPlugin
{
    protected const SUPPORTED_IMAGES = [
        'gif',
        'jpg',
        'jpeg',
        'png',
        'svg',
        'ico',
    ];

    protected helper_plugin_fkstaskrepo $helper;

    public function __construct()
    {
        $this->helper = $this->loadHelper('fkstaskrepo');
    }

    final public function getType(): string
    {
        return 'substition';
    }

    final public function getPType(): string
    {
        return 'block';
    }

    /**
     * Handle matches of the fkstaskrepo syntax
     *
     * @param string $match The match of the syntax
     * @param int $state The state of the handler
     * @param int $pos The position in the document
     * @param Doku_Handler $handler The handler
     * @return array Data for the renderer
     */
    final public function handle($match, $state, $pos, Doku_Handler $handler): array
    {
        $parameters = $this->extractParameters($match);
        return [
            'state' => $state,
            'parameters' => $parameters,
        ];
    }

    abstract protected function extractParameters(string $match): array;

    protected function parseParameters(string $parameterString): array
    {
        //----- default parameter settings
        $params = [
            'year' => null,
            'series' => null,
            'problem' => null,
            'lang' => null,
            'full' => null,
        ];

        //----- parse parameteres into name="value" pairs
        preg_match_all("/(\w+?)=\"(.*?)\"/", $parameterString, $regexMatches, PREG_SET_ORDER);

        for ($i = 0; $i < count($regexMatches); $i++) {
            $name = strtolower($regexMatches[$i][1]);  // first subpattern: name of attribute in lowercase
            $value = $regexMatches[$i][2];              // second subpattern is value
            if (in_array($name, ['year', 'series', 'problem', 'lang'])) {
                $params[$name] = trim($value);
            } else {
                $found = false;
                foreach ($params as $paramName => $default) {
                    if (strcmp($name, $paramName) == 0) {
                        $params[$name] = trim($value);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    msg(sprintf($this->getLang('unexpected_value'), $name), -1);
                }
            }
        }

        return $params;
    }

    /**
     * Renders content of the task
     * @param Doku_Renderer $renderer
     * @param Task $task Task data
     * @param bool $full If the header should contain additional information
     */
    protected function renderTask(Doku_Renderer $renderer, Task $task, bool $full = false): void
    {
        global $conf;
        $renderer->doc .= '<div class="mb-3" data-label="' . $task->label . '">';
        $this->renderHeader($renderer, $task, $full);
        $this->renderImageFigures($renderer, $task);
        $renderer->doc .= '<div>' . ($task->task
                ? $renderer->render_text($task->task)
                : $renderer->render_text($this->helper->getSpecLang('no_translation', $conf['lang']))) . '</div>';
        $this->renderFileAttachments($renderer, $task);
        $renderer->doc .= '<div class="mb-3 d-inline-block">';
        $hasSolution = $this->renderSolutions($renderer, $task);
        $renderer->doc .= join('', array_map(fn($tag) => $this->helper->getTagLink($tag, null, $task->lang), $this->helper->loadTags($task)));
        $this->renderTopics($renderer, $task);
        $renderer->doc .= '</div>';
        if ($hasSolution) {
            $renderer->doc .= '<div class="font-italic pull-right">' . $renderer->render_text($task->origin) . '</div>';
        }
        global $ID;
        if (auth_quickaclcheck($ID) >= AUTH_EDIT && !$task instanceof FSSUTask) {
            $this->renderEditButton($renderer, $task);
        }
        $renderer->doc .= '</div>';
    }

    private function renderEditButton(Doku_Renderer $renderer, Task $task): void
    {
        $form = new Form();
        $form->setHiddenField('do', 'plugin_fkstaskrepo');
        $form->setHiddenField('task[do]', 'edit');
        $form->setHiddenField('task[year]', $task->year);
        $form->setHiddenField('task[series]', $task->series);
        $form->setHiddenField('task[problem]', $task->label);
        $form->setHiddenField('task[lang]', $task->lang);
        $form->addButton('submit', $this->helper->getLang('edit'))->addClass('btn btn-warning');
        $renderer->doc .= $form->toHTML();
    }

    private function renderImageFigures(Doku_Renderer $renderer, Task $task): void
    {
        if (isset($task->figures)) {
            foreach ($task->figures as $figure) {
                if ($this->isImage(ml($figure['path']))) { // Checks if it is an image
                    $renderer->doc .= '
<figure class="col-xl-4 col-lg-5 col-md-6 col-sm-12">
    <img src="' . ml($figure['path']) . '" alt="figure" />
    <figcaption data-lang="' . $task->lang . '" >' .
                        $renderer->render_text($figure['caption']) . '
    </figcaption>
</figure>';
                }
            }
        }
    }

    private function renderFileAttachments(Doku_Renderer $renderer, Task $task): void
    {
        $wrapperRendered = false;
        if (isset($task->figures)) {

            foreach ($task->figures as $figure) {
                if (!$this->isImage(ml($figure['path']))) { // Checks if it is an image
                    if (!$wrapperRendered) {
                        $renderer->doc .= '<div class="task-fileattachments mb-3">';
                        $wrapperRendered = true;
                    }
                    $renderer->doc .= '<div class="task-fileattachments-file">';
                    $renderer->internalmedia($figure['path'], $figure['caption'] ?: null, null, null, null, null, 'linkonly');
                    $renderer->doc .= '</div>';
                }
            }
            if ($wrapperRendered) {
                $renderer->doc .= '</div>';
            }
        }
    }

// TODO
    private function renderTopics(Doku_Renderer $renderer, Task $task): void
    {
        if ($task instanceof FSSUTask) {
            $renderer->doc .= join('', array_map(fn($tag) => $this->helper->getTagLink($tag, null, $task->lang), $task->topics));
        }
    }

    /**
     * Renders links to PDF with solution to specific task. Czech PDF is rendered always though it is on english site.
     * @param Doku_Renderer $renderer
     * @param Task $task
     * @return bool If solution exists
     */
    private function renderSolutions(Doku_Renderer $renderer, Task $task): bool
    {
        global $conf;

        $path = vsprintf($this->helper->getConf('solution_path_' . $conf['lang']), [$task->year, $task->series, $task->label]); // Add path
        $path = file_exists(mediaFN($path)) ? $path : null;

        // Include original cs PDF to en (if exists obviously)
        $original = vsprintf($this->helper->getConf('solution_path_cs'), [$task->year, $task->series, $task->label]); // Add path
        $original = file_exists(mediaFN($original)) && $conf['lang'] !== 'cs' ? $original : null;

        if ($path) {
            $renderer->doc .= '<div class="solution solution-default">';
            $renderer->internalmedia($path, $this->helper->getSpecLang('solution', $conf['lang']), null, null, null, null, 'linkonly');
            $renderer->doc .= '</div>';
        }
        if ($original) {
            $renderer->doc .= '<div class="solution solution-original">';
            $renderer->internalmedia($original, $this->helper->getSpecLang('solution_original', $conf['lang']), null, null, null, null, 'linkonly');
            $renderer->doc .= '</div>';
        }


        return $original || $path;
    }

    private function renderHeader(Doku_Renderer $renderer, Task $task, bool $full = false): void
    {
        $label = $task->label;
        switch ($task->label) {
            case '1':
            case '2':
                $class = 'easy';
                $icon = '<span class="fa fa-smile-o"></span>';
                break;
            case 'E':
            case '7':
                $label = 'E';
                $class = 'experiment';
                $icon = '<span class="fa fa-flask"></span>';
                break;
            case 'S':
            case '8':
                $label = 'S';
                $class = 'serial';
                $icon = '<span class="fa fa-book"></span>';
                break;
            case 'P':
            case '6':
                $label = 'P';
                $icon = '<span class="fa fa-lightbulb-o"></span>';
                $class = 'problem';
                break;
            default:
                $icon = '<span class="fa fa-pencil-square-o"></span>';
                $class = 'default';
        }

        $pointsLabel = null;
        if (isset($task->points)) {
            $pointsLabel = $task->points . ' ';
            switch ($task->points) {
                case 1:
                    $pointsLabel .= $this->helper->getSpecLang('points-N-SG_vote', $task->lang);
                    break;
                case 2:
                case 3:
                case 4:
                    $pointsLabel .= $this->helper->getSpecLang('points-N-PL_vote', $task->lang);
                    break;
                default:
                    $pointsLabel .= $this->helper->getSpecLang('points-G-PL_vote', $task->lang);
            }
        }

        $renderer->doc .= '<h3 class="task-headline task-headline-' . $class . '">' .
            ($pointsLabel ? '<small class="pull-right ml-3">(' . $pointsLabel . ')</small>' : '') .
            $icon .
            ($full
                ? $task->series . '. ' . $this->helper->getSpecLang('series', $task->lang) . ' ' . $task->year . '. ' . $this->helper->getSpecLang('years', $task->lang) . ' - '
                : '') .
            $label . '. ' . $task->name .
            '</h3>';
    }


    /**
     * Decides whether the file is a picture.
     * @param string $file
     * @return bool is image
     */
    protected function isImage(string $file): bool
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)); // Using strtolower to overcome case sensitive
        return in_array($ext, self::SUPPORTED_IMAGES);
    }
}
