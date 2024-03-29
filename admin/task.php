<?php

use dokuwiki\Extension\AdminPlugin;
use dokuwiki\Form\Form;
use dokuwiki\Form\InputElement;
use FYKOS\dokuwiki\Extenstion\PluginTaskRepo\Task;
use FYKOS\dokuwiki\Extenstion\PluginTaskRepo\TexPreproc;

/**
 * Class admin_plugin_fkstaskrepo_task
 * @author Michal Koutný <michal@fykos.cz>
 * @author Štěpán Stenchlák <stenchlak@fykos.cz>
 * @author Michal Červeňák <miso@fykos.cz> PHP7.4 compatiblity
 */
class admin_plugin_fkstaskrepo_task extends AdminPlugin
{

    static array $availableVersions = [1];

    private helper_plugin_fkstaskrepo $helper;

    public function __construct()
    {
        $this->helper = $this->loadHelper('fkstaskrepo');
    }

    /**
     * @param string $language
     * @return string
     */
    public function getMenuText($language): string
    {
        return 'Stáhnout zadání série z Astrid';
    }

    public function getMenuIcon(): string
    {
        $plugin = $this->getPluginName();
        return DOKU_PLUGIN . $plugin . '/task.svg';
    }

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort(): int
    {
        return 10;
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly(): bool
    {
        return false;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle(): void
    {
        global $INPUT;
        $year = $INPUT->int('year', null);
        $series = $INPUT->int('series', null);

        $taskSelect = $INPUT->arr('taskselect', null);

        // Process XML upload
        if ($INPUT->bool('uploadxml')) {
            if ($_FILES['xml_file'] && $_FILES['xml_file']['name']) {
                if ($_FILES['xml_file']['error'] > 0) {
                    msg('Nepodařilo se nahrát XML soubor.', -1);
                    return;
                }
                $this->processSeries(
                    file_get_contents($_FILES['xml_file']['tmp_name']),
                    $INPUT->bool('uploadxmlhard'),
                    $taskSelect
                );
            }
        }
        // Process Astrid download XML
        if ($INPUT->bool('download') && $year && $series) {
            // Tasks
            if ($INPUT->bool('downloadtasks')) {
                // Task XML
                $data = $this->helper->getSeriesData($year, $series);

                if ($data) {
                    $this->processSeries(
                        $data,
                        $INPUT->bool('downloadtaskshard'),
                        $taskSelect
                    );
                } else {
                    msg('Nepodařilo se nahrát XML soubor.', -1);
                }
            }

            // Documents
            foreach ($INPUT->arr('documentselect', null) ?: [] as $ID => $document) {
                $st = $this->helper->downloadDocument($year, $series, $this->getSupportedDocuments()[$ID]['remotepathmask'], $this->getSupportedDocuments()[$ID]['localpathmask']);
                msg(($st ? '<a href="' . ml($st) . '">' : null) . $this->getSupportedDocuments()[$ID]['name'] . ($st ? '</a>' : null), $st ? 1 : -1);
            }
        }
    }

    public function html(): void
    {
        ptln('<h1>' . $this->getMenuText('cs') . '</h1>');
        $form = new Form();
        $form->addClass('task-repo-edit');
        $form->attrs(['class' => $this->getPluginName(), 'enctype' => 'multipart/form-data']);

        $form->addHTML('<p>Vyberte číslo série a ročníku a klikněte na příslušné tlačítko. Dojde ke stažení, popřípadě aktualizaci, dat na webu z <a href="https://astrid.fykos.cz">astrid.fykos.cz</a>. Pokud přidáváte novou sérii, nezapomeňte upravit odkazy v hlavním menu jak v české, tak i v anglické verzi. Vše ostatní by mělo být automatické.</p>');

        $form->addTagOpen('div')->addClass('form-group');
        $inputElement = new dokuwiki\Form\InputElement('number', 'year', 'Číslo ročníku');
        $inputElement->attrs(['class' => 'form-control']);
        $form->addElement($inputElement);
        $form->addTagClose('div');

        $form->addTagOpen('div')->addClass('form-group');
        $inputElement = new dokuwiki\Form\InputElement('number', 'series', 'Číslo série');
        $inputElement->attrs(['class' => 'form-control']);
        $form->addElement($inputElement);
        $form->addTagClose('div');

        // List of tasks to download
        $this->helper->addTaskSelectTable($form);
        $form->addHTML('<hr/>');

        $form->addHTML('<hr/>');

        // Some stuff to decide what do to...
        $form->addTagOpen('div');
        $form->addCheckbox('downloadtasks', 'Stahovat vůbec zadání?')->attr('checked', 'checked');
        $form->addTagClose('div');

        $form->addTagOpen('div');
        $form->addCheckbox('downloadtaskshard', 'Přepsat existující příklady na webu.');
        $form->addTagClose('div');

        $this->addDocumentSelectList($form);

        $form->addButton('download', 'Importovat zadání, brožurku a seriál této série.')->addClass('btn btn-primary d-block mb-3');

        $form->addHTML('<small class="form-text">Stáhne z Astrid české a anglické zadání, brožurku v PDF a seriál v PDF, pokud jsou zaškrtnuté.</small>');

        $form->addHTML('<hr/>');

        $form->addButton('uploadxml', 'Nahrát XML ručně ze souboru.')->addClass('btn btn-warning d-block mb-3');
        $form->addCheckbox('uploadxmlhard', 'Přepsat existující příklady na webu.');
        $form->addElement((new InputElement('file', 'xml_file'))->addClass('d-block mt-3'));

        $form->addHTML('<small class="form-text">Tuto možnost používejte pouze tehdy, pokud není možné automaticky importovat z Astrid. Vyberte prosím pouze z tabulky příklady, které chcete importovat.</small>');
        $form->addHTML('<hr/>');
        echo $form->toHTML();
    }

    /**
     * Process XML and creates tasks
     * @param string $content XML content
     * @param bool $hard overwrite existing tasks
     * @param $taskSelect @see $this->helper->addTaskSelectTable()
     */
    private function processSeries(string $content, bool $hard, $taskSelect): void
    {
        $seriesXML = simplexml_load_string($content);

        $deadline = $seriesXML->deadline;
        $deadlinePost = $seriesXML->{'deadline-post'};

        $m = [];
        preg_match('/[0-9]+/', $seriesXML->contest, $m);
        $year = (int)$m[0]; // FYKOSXX

        $series = (int)$seriesXML->number;

        foreach ($this->helper->getSupportedLanguages() as $lang) {
            // Test if any task in current language is selected
            $somethingChosen = false;
            foreach ($taskSelect[$lang] ?: [] as $taskSelected) {
                if ($taskSelected) {
                    $somethingChosen = true;
                    break;
                }
            }
            if (!$somethingChosen) {
                continue;
            }

            $pagePath = sprintf($this->getConf('page_path_mask_' . $lang), $year, $series);
            if ($pagePath == "") {
                msg('No page path defined for language ' . $lang, -1);
                continue;
            }

            // Loads template for page
            $pageTemplate = io_readFile(wikiFN($this->getConf('series_template')));

            // Replace data in template
            $pageContent = $this->replaceVariables([
                'human-deadline' => date($this->helper->getSpecLang('deadline-format', $lang), strtotime($deadline)),
                'human-deadline-post' => date($this->helper->getSpecLang('deadline-post-format', $lang), strtotime($deadlinePost)),
                'lang' => $lang,
                'year' => $year,
                'series' => $series,
            ],
                $pageTemplate);

            // Saves problems
            foreach ($seriesXML->problems->children() as $problem) {
                $this->createTask($problem, $year, $series, $lang, $hard, $taskSelect);
            }

            // Saves pages with problems
            io_saveFile(wikiFN($pagePath), $pageContent);

            msg(sprintf('<a href="%s">Stránka série v jazyce ' . $lang . '</a>.', wl($pagePath, null, true), $pagePath), 1);
        }
    }

    /**
     * Saves specific problem from XMLElement
     * @param SimpleXMLElement $problem
     * @param $year
     * @param $series
     * @param $lang
     * @param bool $hard overwrite existing task
     * @param $taskSelect @see $this->helper->addTaskSelectTable()
     * @return bool
     */
    private function createTask(SimpleXMLElement $problem, int $year, int $series, string $lang, bool $hard, $taskSelect): bool
    {
        // Test, if the task is selected
        if (!$taskSelect[$lang][$this->helper->labelToNumber($problem->label)]) {
            return true;
        }

        $task = new Task($year, $series, (string)$problem->label, $lang);
        $exists = $this->helper->loadTask($task);

        if (!$hard && $exists) {
            msg("{$task->name} ($year-$series-{$task->label}-$lang) byla přeskočena.");
            return true;
        }

        // Save figures
        $this->helper->saveFiguresRawData($task, $this->extractFigures($problem, $lang));

        foreach ($problem->children() as $k => $child) {
            if ($this->hasLang($child, $lang)) {
                switch ($k) {
                    case 'number':
                        $task->number = (int)$child;
                        break;
                    case'name':
                        $task->name = (string)$child;
                        break;
                    case 'origin':
                        $task->origin = (string)$child;
                        break;
                    case'points':
                        $task->points = (int)$child;
                        break;
                    case 'task':
                        $task->task = (new TexPreproc())->preproc((string)$child);
                        break;
                    case 'authors':
                        $authors = (array)$child->children();
                        if (isset($authors['author'])) {
                            $task->authors = is_scalar($authors['author']) ? [$authors['author']] : $authors['author'];
                        }
                        break;
                    case 'solution-authors':
                        $solutionAuthors = (array)$child->children();
                        if ($solutionAuthors['solution-author']) {
                            $task->solutionAuthors = is_scalar($solutionAuthors['solution-author']) ? [$solutionAuthors['solution-author']] : $solutionAuthors['solution-author'];
                        }
                        break;
                }
            }
        }
        $this->helper->saveTask($task);

        msg("{$task->name} ($year-$series-{$task->label}-$lang)", 1);

        $this->helper->storeTags($task->year, $task->series, $task->label, (array)$problem->topics->topic);
        return true;
    }

    /**
     * Checks if the SimpleXMLElement $e has set the specific lang, or nothing
     * @param SimpleXMLElement $e
     * @param string $lang
     * @return bool
     */
    private function hasLang(SimpleXMLElement $e, string $lang): bool
    {
        return (($lang == (string)$e->attributes(helper_plugin_fkstaskrepo::XMLNamespace)->lang) ||
            (string)$e->attributes(helper_plugin_fkstaskrepo::XMLNamespace)->lang == "");
    }

    /**
     * @param SimpleXMLElement $problem
     * @param $lang
     * @return array
     * @todo Solve languages
     */
    private function extractFigures(SimpleXMLElement $problem, string $lang): array
    {
        $figuresData = [];
        if ((string)$problem->figures != "") {
            foreach ($problem->figures->figure as $figure) {
                if ($this->hasLang($figure, $lang)) {
                    $simpleFigure = [];
                    $simpleFigure['caption'] = (string)$figure->caption;
                    foreach ($figure->data as $data) {
                        $type = (string)$data->attributes()->extension;
                        $simpleFigure['data'][$type] = trim((string)$data);
                    }
                    $figuresData[] = $simpleFigure;
                }
            }
        }

        return $figuresData;
    }

    /**
     * Replaces data in string
     * @param array $parameters
     * @param string $template
     * @return string
     */
    private function replaceVariables(array $parameters, string $template): string
    {
        $that = $this;

        return preg_replace_callback('/@([^@]+)@/',
            function ($match) use ($parameters, $that) {
                $key = $match[1];
                if (!isset($parameters[$key])) {
                    msg(sprintf($that->getLang('undefined_template_variable'), $key));
                    return '';
                } else {
                    return $parameters[$key];
                }
            },
            $template);
    }

    private function addDocumentSelectList(Form $form): void
    {
        foreach ($this->getSupportedDocuments() as $ID => $document) {
            $form->addTagOpen('div');
            $form->addCheckbox('documentselect[' . $ID . ']', $document['name'])->attr('checked', 'checked');
            $form->addTagClose('div');
        }
    }

    private function getSupportedDocuments(): array
    {
        return [
            [
                'name' => 'Brožurka série v PDF',
                'remotepathmask' => $this->getConf('remote_brochure_path_mask'),
                'localpathmask' => $this->getConf('brochure_path_cs'),
            ],
            [
                'name' => 'Český text seriálu v PDF',
                'remotepathmask' => $this->getConf('remote_serial_path_mask_cs'),
                'localpathmask' => $this->getConf('serial_path_cs'),
            ],
            [
                'name' => 'Anglický text seriálu v PDF',
                'remotepathmask' => $this->getConf('remote_serial_path_mask_en'),
                'localpathmask' => $this->getConf('serial_path_en'),
            ],
        ];
    }
}
