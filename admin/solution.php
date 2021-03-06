<?php

use dokuwiki\Extension\AdminPlugin;
use dokuwiki\Form\Form;

if (!defined('DOKU_INC')) die();

/**
 * Class admin_plugin_fkstaskrepo_solution
 * @author Štěpán Stenchlák <stenchlak@fykos.cz>
 * @author  Michal Červeňák <miso@fykos.cz> PHP7.4 compatiblity
 */
class admin_plugin_fkstaskrepo_solution extends AdminPlugin {

    static $availableVersions = [1];

    private $helper;

    public function __construct() {
        $this->helper = $this->loadHelper('fkstaskrepo');
    }

    public function getMenuText($language): string {
        return 'Stáhnout vzorová řešení z Astrid';
    }

    public function getMenuIcon(): string {
        $plugin = $this->getPluginName();
        return DOKU_PLUGIN . $plugin . '/solution.svg';
    }

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort(): int {
        return 10;
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly(): bool {
        return false;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle(): void {
        global $INPUT;
        $year = $INPUT->int('year', null);
        $series = $INPUT->int('series', null);

        $taskSelect = $INPUT->arr('taskselect', null);

        // Process solution download
        if ($INPUT->bool('downloadsolutions') && $year && $series) {
            foreach (['cs'] as $language) { // For now, only czech language is supported
                foreach ($this->helper->getSupportedTasks() as $taskNumber => $task) {
                    // Test, if the task is selected
                    if ($taskSelect[$language][$taskNumber]) {
                        $st = $this->helper->downloadSolution($year, $series, $task);
                        msg(($st ? '<a href="' . ml($st) . '">' : null) . 'Řešení úlohy ' . $task . ($st ? '</a>' : null), $st ? 1 : -1);
                    }
                }
            }
        }
    }

    public function html(): void {
        global $ID;
        ptln('<h1>' . $this->getMenuText('cs') . '</h1>');
        $form = new Form();
        $form->addClass('task-repo-edit');
        $form->attrs(['class' => $this->getPluginName(), 'enctype' => 'multipart/form-data']);

        $form->addHTML('<p>Vyberte číslo série a ročníku a klikněte na příslušné tlačítko. Dojde ke stažení, popřípadě aktualizaci, dat na webu z <a href="astrid.fykos.cz">astrid.fykos.cz</a>. Pokud přidáváte novou sérii, nezapomeňte upravit odkazy v hlavním menu jak v české, tak i v anglické verzi. Vše ostatní by mělo být automatické.</p>');

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

        $this->helper->addTaskSelectTable($form, ['cs']); // For now, only czech language is supported

        $form->addHTML('<hr/>');

        $form->addButton('downloadsolutions', 'Stáhnout a zobrazit na webu řešení této série.')->addClass('btn btn-danger');
        $form->addHTML('<small class="form-text text-danger">Stáhne z Astrid řešení k jednotlivým příkladům v PDF a zobrazí je na webu.</small>');

        $form->setHiddenField('id', $ID);
        $form->setHiddenField('do', 'admin');

        echo $form->toHTML();
    }
}
