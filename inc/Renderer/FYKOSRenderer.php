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
     * @param Task $data Task data
     * @param bool $full If the header should contain additional information
     */
    public function render(Doku_Renderer $renderer, Task $data, bool $full = false): void
    {
        $renderer->doc .= '<div class="mb-3" data-label="' . $data->label . '">';
        $renderer->doc .= $this->renderHeader($data, $full);
        $renderer->doc .= $this->renderImageFigures($renderer, $data);
        $renderer->doc .= $this->renderTask($renderer, $data);
        $this->renderFileAttachments($renderer, $data);
        $renderer->doc .= '<div class="mb-3 d-inline-block">';
        $hasSolution = $this->renderSolutions($renderer, $data);
        $renderer->doc .= $this->renderTags($data);
        $renderer->doc .= '</div>';
        if ($hasSolution) {
            $renderer->doc .= $this->renderOrigin($renderer, $data);
        }
        global $ID;
        if (auth_quickaclcheck($ID) >= AUTH_EDIT) {
            $renderer->doc .= $this->renderEditButton($data);
        }
        $renderer->doc .= '</div>';
    }

    private function renderEditButton(Task $data): string
    {
        $form = new Form();
        $form->setHiddenField('do', 'plugin_fkstaskrepo');
        $form->setHiddenField('task[do]', 'edit');
        $form->setHiddenField('task[year]', $data->year);
        $form->setHiddenField('task[series]', $data->series);
        $form->setHiddenField('task[problem]', $data->label);
        $form->setHiddenField('task[lang]', $data->lang);
        $form->addButton('submit', $this->helper->getLang('edit'))->addClass('btn btn-warning');
        return $form->toHTML();
    }

    private function renderImageFigures(Doku_Renderer $renderer, Task $data): string
    {
        $html = '';
        if (isset($data->figures)) {
            foreach ($data->figures as $figure) {
                if ($this->isImage(ml($figure['path']))) { // Checks if it is an image
                    $html .= '<figure class="col-xl-4 col-lg-5 col-md-6 col-sm-12">';
                    $html .= '<img src="' . ml($figure['path']) . '" alt="figure" />';
                    $html .= '<figcaption data-lang="' . $data->lang . '" >';
                    $html .= $renderer->render_text($figure['caption']);
                    $html .= '</figcaption>';
                    $html .= '</figure>';
                }
            }
        }
        return $html;
    }

    private function renderFileAttachments(Doku_Renderer $renderer, Task $data): void
    {
        $wrapperRendered = false;
        if (isset($data->figures)) {

            foreach ($data->figures as $figure) {
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

    private function renderTags(Task $data): string
    {
        $tags = $this->helper->loadTags($data->year, $data->series, $data->label);
        $html = '';
        foreach ($tags as $tag) {
            $html .= $this->helper->getTagLink($tag, null, $data->lang);
        }
        return $html;
    }

    private function renderOrigin(Doku_Renderer $renderer, Task $data): string
    {
        return '<div class="font-italic pull-right">' . $renderer->render_text($data->origin) . '</div>';
    }

    private function renderTask(Doku_Renderer $renderer, Task $data): string
    {
        global $conf;
        if ($data->task) {
            return '<div>' . $renderer->render_text($data->task) . '</div>';
        } else {
            return '<div>' . $renderer->render_text($this->helper->getSpecLang('no_translation', $conf['lang'])) . '</div>';
        }
    }

    /**
     * Renders links to PDF with solution to specific task. Czech PDF is rendered always though it is on english site.
     * @param Doku_Renderer $renderer
     * @param Task $data
     * @return bool If solution exists
     */
    private function renderSolutions(Doku_Renderer $renderer, Task $data): bool
    {
        global $conf;

        $path = vsprintf($this->helper->getConf('solution_path_' . $conf['lang']), [$data->year, $data->series, $data->label]); // Add path
        $path = file_exists(mediaFN($path)) ? $path : null;

        // Include original cs PDF to en (if exists obviously)
        $original = vsprintf($this->helper->getConf('solution_path_cs'), [$data->year, $data->series, $data->label]); // Add path
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

    private function renderHeader(Task $data, bool $full = false): string
    {
        $pointsLabel = $this->getPointsLabel($data);
        $problemLabel = $data->label . '. ';//. $this->helper->getSpecLang('label', $data['lang']);

        $seriesLabel = $this->getSeriesLabel($data);
        $yearLabel = $this->getYearLabel($data);
        $html = '<h3 class="task-headline task-headline-' . $this->getHeadlineClass($data) . '">';
        $html .= $pointsLabel ? '<small class="pull-right ml-3">(' . $pointsLabel . ')</small>' : '';
        $html .= $this->getProblemIcon($data);
        if ($full) {
            $html .= $seriesLabel . ' ' . $yearLabel . ' - ' . $problemLabel . ' ' . $data->name;
        } else {
            $html .= $problemLabel . ' ' . $data->name;
        }
        $html .= '</h3>';
        return $html;
    }

    private function getHeadlineClass(Task $data): string
    {
        switch ($data->label) {
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

    private function getSeriesLabel(Task $data): string
    {
        return $data->series . '. ' . $this->helper->getSpecLang('series', $data->lang);
    }

    private function getYearLabel(Task $data): string
    {
        return $data->year . '. ' . $this->helper->getSpecLang('years', $data->lang);
    }

    private function getProblemIcon(Task $data): string
    {
        switch ($data->label) {
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

    /**
     * Decides whether the file is a picture.
     * @param $file
     * @return bool is image
     */
    private function isImage($file): bool
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)); // Using strtolower to overcome case sensitive
        return in_array($ext, self::SUPPORTED_IMAGES);
    }
}
