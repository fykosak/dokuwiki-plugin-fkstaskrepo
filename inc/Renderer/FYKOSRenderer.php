<?php

namespace FYKOS\dokuwiki\Extenstion\PluginTaskRepo;

use Doku_Renderer;
use dokuwiki\Form\Form;

/**
 * Class FYKOSRenderer
 * @author Michal Koutný <michal@fykos.cz>
 * @author Michal Červeňák <miso@fykos.cz>
 * @author Štěpán Stenchlák <stenchlak@fykos.cz>
 */
class FYKOSRenderer extends AbstractRenderer
{

    /**
     * Renders content of the task
     * @param Doku_Renderer $renderer
     * @param Task $task Task data
     * @param bool $full If the header should contain additional information
     */
    public function render(Doku_Renderer $renderer, Task $task, bool $full = false): void
    {
        $renderer->doc .= '<div class="mb-3" data-label="' . $task->label . '">';
        $renderer->doc .= $this->renderHeader($task, $full);
        $renderer->doc .= $this->renderImageFigures($renderer, $task);
        $renderer->doc .= $this->renderTask($renderer, $task);
        $this->renderFileAttachments($renderer, $task);
        $renderer->doc .= '<div class="mb-3 d-inline-block">';
        $hasSolution = $this->renderSolutions($renderer, $task);
        $renderer->doc .= $this->renderTags($task);
        $renderer->doc .= $this->renderTopics($task);
        $renderer->doc .= '</div>';
        if ($hasSolution) {
            $renderer->doc .= $this->renderOrigin($renderer, $task);
        }
        global $ID;
        if (auth_quickaclcheck($ID) >= AUTH_EDIT && !$task instanceof FSSUTask) {
            $renderer->doc .= $this->renderEditButton($task);
        }
        $renderer->doc .= '</div>';
    }

    private function renderEditButton(Task $task): string
    {
        $form = new Form();
        $form->setHiddenField('do', 'plugin_fkstaskrepo');
        $form->setHiddenField('task[do]', 'edit');
        $form->setHiddenField('task[year]', $task->year);
        $form->setHiddenField('task[series]', $task->series);
        $form->setHiddenField('task[problem]', $task->label);
        $form->setHiddenField('task[lang]', $task->lang);
        $form->addButton('submit', $this->helper->getLang('edit'))->addClass('btn btn-warning');
        return $form->toHTML();
    }

    private function renderImageFigures(Doku_Renderer $renderer, Task $task): string
    {
        $html = '';
        if (isset($task->figures)) {
            foreach ($task->figures as $figure) {
                if ($this->isImage(ml($figure['path']))) { // Checks if it is an image
                    $html .= '
<figure class="col-xl-4 col-lg-5 col-md-6 col-sm-12">
    <img src="' . ml($figure['path']) . '" alt="figure" />
    <figcaption data-lang="' . $task->lang . '" >' .
                        $renderer->render_text($figure['caption']) . '
    </figcaption>
</figure>';
                }
            }
        }
        return $html;
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

    private function renderTags(Task $task): string
    {
        return join('', array_map(fn($tag) => $this->helper->getTagLink($tag, null, $task->lang), $this->helper->loadTags($task)));
    }

// TODO
    private function renderTopics(Task $task): string
    {
        if ($task instanceof FSSUTask) {
            return join('', array_map(fn($tag) => $this->helper->getTagLink($tag, null, $task->lang), $task->topics));
        }
        return '';
    }

    private function renderOrigin(Doku_Renderer $renderer, Task $task): string
    {
        return '<div class="font-italic pull-right">' . $renderer->render_text($task->origin) . '</div>';
    }

    private function renderTask(Doku_Renderer $renderer, Task $task): string
    {
        global $conf;
        if ($task->task) {
            return '<div>' . $renderer->render_text($task->task) . '</div>';
        } else {
            return '<div>' . $renderer->render_text($this->helper->getSpecLang('no_translation', $conf['lang'])) . '</div>';
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

        if ($original) {
            $renderer->doc .= '<div class="solution solution-original">';
            $renderer->internalmedia($original, $this->helper->getSpecLang('solution_original', $conf['lang']), null, null, null, null, 'linkonly');
            $renderer->doc .= '</div>';
        }
        if ($path) {
            $renderer->doc .= '<div class="solution solution-default">';
            $renderer->internalmedia($path, $this->helper->getSpecLang('solution', $conf['lang']), null, null, null, null, 'linkonly');
            $renderer->doc .= '</div>';
        }

        return $original || $path;
    }

    private function renderHeader(Task $task, bool $full = false): string
    {
        $pointsLabel = $this->getPointsLabel($task);
        $problemLabel = $task->label . '. ';//. $this->helper->getSpecLang('label', $task['lang']);

        $seriesLabel = $this->getSeriesLabel($task);
        $yearLabel = $this->getYearLabel($task);
        $html = '<h3 class="task-headline task-headline-' . $this->getHeadlineClass($task) . '">';
        $html .= $pointsLabel ? '<small class="pull-right ml-3">(' . $pointsLabel . ')</small>' : '';
        $html .= $this->getProblemIcon($task);
        if ($full) {
            $html .= $seriesLabel . ' ' . $yearLabel . ' - ' . $problemLabel . ' ' . $task->name;
        } else {
            $html .= $problemLabel . ' ' . $task->name;
        }
        $html .= '</h3>';
        return $html;
    }

    private function getHeadlineClass(Task $task): string
    {
        switch ($task->label) {
            case '1':
            case '2':
                return 'easy';
            case 'E':
                return 'experiment';
            case 'S':
            case 'C':
                return 'serial';
            case'P':
                return 'problem';
            default:
                return 'default';
        }
    }

    private function getPointsLabel(Task $task): ?string
    {
        if (!isset($task->points)) {
            return null;
        }

        switch ($task->points) {
            case 1:
                return $task->points . ' ' . $this->helper->getSpecLang('points-N-SG_vote', $task->lang);
            case 2:
            case 3:
            case 4:
                return $task->points . ' ' . $this->helper->getSpecLang('points-N-PL_vote', $task->lang);
            default:
                return $task->points . ' ' . $this->helper->getSpecLang('points-G-PL_vote', $task->lang);
        }

    }

    private function getSeriesLabel(Task $task): string
    {
        return $task->series . '. ' . $this->helper->getSpecLang('series', $task->lang);
    }

    private function getYearLabel(Task $task): string
    {
        return $task->year . '. ' . $this->helper->getSpecLang('years', $task->lang);
    }

    private function getProblemIcon(Task $task): string
    {
        switch ($task->label) {
            case '1':
            case '2':
                return '<span class="fa fa-smile-o"></span>';
            case 'E':
                return '<span class="fa fa-flask"></span>';
            case 'S':
            case 'C':
                return '<span class="fa fa-book"></span>';
            case'P':
                return '<span class="fa fa-lightbulb-o"></span>';
            default:
                return '<span class="fa fa-pencil-square-o"></span>';
        }
    }
}
