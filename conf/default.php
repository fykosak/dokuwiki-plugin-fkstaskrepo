<?php
/**
 * Default settings for the fkstaskrepo plugin
 *
 * @author Michal Koutný <michal@fykos.cz>
 */

$conf['remote_path_mask']     = '/vyfuk%1$s/batch%2$s/out/zadaniWeb%2$s.xml';

$conf['series_template'] = 'system:series_template';
$conf['task_template'] = 'system:task_template';
$conf['task_template_search'] = 'system:task_template';

$conf['page_path_mask_cs'] = 'ulohy:r%1$s:s%2$s';
$conf['page_path_mask_en'] = 'task:r%1$s:s%2$s';

$conf['brochure_path_cs']=":archiv:rocnik@year@:serie@series@.pdf";
$conf['brochure_path_en']=":archive:year@year@:series@series@.pdf";

$conf['solution_path_cs']=":archiv:rocnik@year@:problem@label@.pdf";
$conf['solution_path_en']=":archive:year@year@:problems@label@.pdf";

$conf['im_convert']='';