<?php

namespace FYKOS\dokuwiki\Extenstion\PluginTaskRepo;

use Doku_Renderer;
use dokuwiki\Form\Form;

class VyfukRenderer extends AbstractRenderer {

    /**
     * Renders content of the task
     * @param Doku_Renderer $renderer
     * @param Task $data Task data
     * @param bool $full If the header should contain additional information
     */
    public function render(Doku_Renderer $renderer, Task $data, bool $full = false): void {
        $renderer->doc .= '<div class="mb-3" data-label="' . $data->getLabel() . '">';
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

    private function renderEditButton(Task $data): string {
        $form = new Form();
        $form->setHiddenField('do', 'plugin_fkstaskrepo');
        $form->setHiddenField('task[do]', 'edit');
        $form->setHiddenField('task[year]', $data->getYear());
        $form->setHiddenField('task[series]', $data->getSeries());
        $form->setHiddenField('task[problem]', $data->getLabel());
        $form->setHiddenField('task[lang]', $data->getLang());
        $form->addButtonHTML('submit', '<i class="fa fa-pencil" aria-hidden="true"></i> ' .
            $this->helper->getLang('edit'))->addClass('btn btn-primary pull-right');
        $form->addHTML('<div class="clearfix"></div>');
        return $form->toHTML();
    }

    private function renderImageFigures(Doku_Renderer $renderer, Task $data): string {
        $html = '';
        if (is_array($data->getFigures())) {
            foreach ($data->getFigures() as $figure) {
                if ($this->isImage(ml($figure['path']))) { // Checks if it is an image
                    $html .= '<figure class="col-xl-4 col-lg-5 col-md-6 col-sm-12 ms-2">';
                    $html .= '<img src="' . ml($figure['path']) . '" alt="figure" />';
                    $html .= '<figcaption data-lang="' . $data->getLang() . '" >';
                    $html .= $renderer->render_text($figure['caption']);
                    $html .= '</figcaption>';
                    $html .= '</figure>';
                }
            }
        }
        return $html;
    }

    private function renderFileAttachments(Doku_Renderer $renderer, Task $data): void {
        $wrapperRendered = false;
        if (is_array($data->getFigures())) {

            foreach ($data->getFigures() as $figure) {
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

    private function renderTags(Task $data): string {
        $tags = $this->helper->loadTags($data->getYear(), $data->getSeries(), $data->getLabel());
        $html = '';
        foreach ($tags as $tag) {
            $html .= $this->helper->getTagLink($tag, null, $data->getLang());
        }
        return $html;
    }

    private function renderOrigin(Doku_Renderer $renderer, Task $data): string {
        return '<div class="font-italic pull-right">' . $renderer->render_text($data->getOrigin()) . '</div>';
    }

    private function renderTask(Doku_Renderer $renderer, Task $data): string {
        global $conf;
        if ($data->getTask()) {
            return '<div>' . $renderer->render_text($data->getTask()) . '</div>';
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
    private function renderSolutions(Doku_Renderer $renderer, Task $data): bool {
        global $conf;

        $path = vsprintf($this->helper->getConf('solution_path_' . $conf['lang']), [$data->getYear(), $data->getSeries(), $data->getLabel()]); // Add path
        $path = file_exists(mediaFN($path)) ? $path : null;

        // Include original cs PDF to en (if exists obviously)
        $original = vsprintf($this->helper->getConf('solution_path_cs'), [$data->getYear(), $data->getSeries(), $data->getLabel()]); // Add path
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

    private function renderHeader(Task $data, bool $full = false): string {
        $pointsLabel = $this->getPointsLabel($data);
        $problemLabel = $data->getLabel() . '. ';
        $problemName = $data->getName();
        $seriesLabel = $this->getSeriesLabel($data);
        $yearLabel = $this->getYearLabel($data);
        $categoryIcons = $this->getCategoryIcons($data);

        $html = '<div class="d-flex align-items-center text-primary"><h3>';
        $html .= $this->getProblemIcon($data) . '&nbsp;';
        if ($full) {
            $html .= $seriesLabel . ' ' . $yearLabel . ' - ';
        }
        $html .= $problemLabel . ' ' . $problemName . '</h3>' . $categoryIcons;
        $html .= $pointsLabel ? '<span class="ms-auto ps-2">(' . $pointsLabel . ')</span>' : '';
        $html .= '</div>';
        return $html;
    }

    private function getPointsLabel(Task $data): ?string {
        if (!$data->getPoints()) {
            return null;
        }

        switch ($data->getPoints()) {
            case 1:
                return $data->getPoints() . ' ' . $this->helper->getSpecLang('points-N-SG_vote', $data->getLang());
            case 2:
            case 3:
            case 4:
                return $data->getPoints() . ' ' . $this->helper->getSpecLang('points-N-PL_vote', $data->getLang());
            default:
                return $data->getPoints() . ' ' . $this->helper->getSpecLang('points-G-PL_vote', $data->getLang());
        }
    }

    private function getSeriesLabel(Task $data): string {
        return $data->getSeries() . '. ' . $this->helper->getSpecLang('series', $data->getLang());
    }

    private function getYearLabel(Task $data): string {
        return $data->getYear() . '. ' . $this->helper->getSpecLang('years', $data->getLang());
    }

    private function getProblemIcon(Task $data): string {
        if ($data->getSeries() > 6) {
            // Icons for classic series
            switch ($data->getLabel()) {
                case '1':
                    return '<i class="fa fa-question-circle-o"></i>';
                case '2':
                    return '<i class="fa fa-calculator"></i>';
                case '3':
                    return '<i class="fa fa-flask"></i>';
                default:
                    return '<i class="fa fa-pencil"></i>';
            }
        } else {
            //Icons for holiday series
            switch ($data->getLabel()) {
                case '1':
                    return '<i class="fa fa-smile-o"></i>';
                case '2':
                    return '<i class="fa fa-calculator"></i>';
                case '5':
                    return '<i class="fa fa-cogs"></i>';
                case 'E':
                    return '<i class="fa fa-flask"></i>';
                case 'V':
                case 'C':
                    return '<i class="fa fa-book"></i>';
                default:
                    return '<i class="fa fa-pencil"></i>';
            }
        }
    }

    /**
     * Decides whether the file is a picture.
     * @param $file
     * @return bool is image
     */
    private function isImage($file): bool {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)); // Using strtolower to overcome case sensitive
        return in_array($ext, self::SUPPORTED_IMAGES);
    }

    /**
     * Get category icons for specific tasks
     * @param Task $data
     * @return string html of icons
     */
    private function getCategoryIcons(Task $data): string {
        $html = ' <div class="d-flex ms-1">';
        $html .= $this->getCatCircle(6);
        $html .= $this->getCatCircle(7);
        // Add 8th and 9th grade icon on all tasks except for
        // 1st task of classic series
        if (!($data->getNumber() == 1 && $data->getSeries() < 7)) {
            $html .= $this->getCatCircle(8);
            $html .= $this->getCatCircle(9);
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Get category circle
     * @param int $num
     * @return string
     */
    private function getCatCircle(int $num) {
        return sprintf('<div class="cat-circle" data-toggle="tooltip" data-placement="top"
            title="Úloha je určena pro %1$d. třídy ZŠ a odpovídající ročníky gymnázií.">
            <span class="user-select-none">%1$d</span></div>', $num);
    }
}
