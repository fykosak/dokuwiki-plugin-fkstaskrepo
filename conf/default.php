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

$conf['page_path_mask_cs'] = "rocnik@year@:ulohy:serie@series@";
$conf['page_path_mask_en'] = "year@year@:tasks:series@series@";

$conf['brochure_path_cs']='rocnik%1$02d:ulohy:pdf:%2$d:brozurka_%1$02d_%2$d.pdf';
$conf['brochure_path_en']='year%1$02d:tasks:pdf:%2$d:brochure_%1$02d_%2$d.pdf';

$conf['solution_path_cs']='rocnik%1$02d:ulohy:pdf:%2$d:priklad_%1$02d_%2$d_%3$s.pdf';
$conf['solution_path_en']='year%1$02d:tasks:pdf:%2$d:task_%1$02d_%2$d_%3$s.pdf';

$conf['attachment_path_cs'] = 'rocnik%1$02d:ulohy:prilohy:%2$d:%3$s';
$conf['attachment_path_en'] = 'year%1$02d:tasks:attachments:%2$d:%3$s';

$conf['task_data_meta_path'] = 'tasks:%1$d:%2$d:%3$s.json';
