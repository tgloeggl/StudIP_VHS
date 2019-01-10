<?php
require_once 'app/controllers/news.php';


class IntranetStartController extends StudipController {

    public function __construct($dispatcher)
    {
        parent::__construct($dispatcher);
        $this->plugin = $dispatcher->plugin;
        Navigation::activateItem('start');
        PageLayout::addStylesheet($this->plugin->getPluginURL().'/assets/no_tabs.css');
    }

    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        PageLayout::addStylesheet($this->plugin->getPluginURL().'/assets/intranet_start.css');
        PageLayout::setTitle(_("Meine Startseite"));
        $this->set_layout($GLOBALS['template_factory']->open('layouts/base'));
    }

    public function index_action($inst_id)
    {
        //get seminars ($inst_id)
        $this->intranet_courses = IntranetConfig::find($inst_id)->getRelatedCourses();        
        foreach($this->intranet_courses as $course){
            $config = IntranetSeminar::find([$course->id, $inst_id]);
            if ($config && $config->show_news){
                $this->newsTemplates[$course->id] = $this->getNewsTemplateForSeminar($course->id);
                $this->newsCaptions[$course->id] = $config->news_caption;
            }
        }

        //get permission of currentUser (autor/dozent)
        
//        $sem_id_mitarbeiterinnen = Config::get()->getValue('INTRANET_SEMID_MITARBEITERINNEN');
//        
//        $sem_id_projektbereich = Config::get()->getValue('INTRANET_SEMID_PROJEKTBEREICH');
//        
//        global $perm; 
//        $this->mitarbeiter_admin = $perm->have_studip_perm('dozent', $sem_id_mitarbeiterinnen);
//        $this->projekt_admin = $perm->have_studip_perm('dozent', $sem_id_projektbereich);
//        
//        $this->edit_link_internnews = URLHelper::getLink("dispatch.php/news/edit_news/new/". $sem_id_mitarbeiterinnen);
//        $this->edit_link_projectnews = URLHelper::getLink("dispatch.php/news/edit_news/new/" . $sem_id_projektbereich);
//        $this->edit_link_files = URLHelper::getLink("folder.php?cid=" . $sem_id_projektbereich . "&cmd=tree");
        
        //get news of connected seminars

//        $dispatcher = new StudipDispatcher();
//        $controller = new NewsController($dispatcher);
//        $response = $controller->relay('news/display/' . $sem_id_mitarbeiterinnen);
//        //$response = $controller->relay('news/display/9fc5dd6a84acf0ad76d2de71b473b341'); //localhost
//        $this->internnewstemplate = $GLOBALS['template_factory']->open('shared/string');
//        $this->internnewstemplate->content = $response->body;
//        
//
//        if (StudipNews::CountUnread() > 0) {
//            $navigation = new Navigation('', PluginEngine::getLink($this, array(), 'read_all'));
//            $navigation->setImage(Icon::create('refresh', 'clickable', ["title" => _('Alle als gelesen markieren')]));
//            $icons[] = $navigation;
//        }
//
//        $this->internnewstemplate->icons = $icons;
        
        //get special dates (maybe)
//$this->birthday_dates = IntranetDate::findBySQL("type = 'birthday' AND begin = ?", array(date('d.m.Y', time())));

        //get new and recently visited courses of user
        $statement = DBManager::get()->prepare("SELECT s.Seminar_id, s.Name, ouv.visitdate, ouv.type "
                . "FROM seminare as s "
                . "LEFT JOIN object_user_visits as ouv ON (s.Seminar_id = ouv.object_id) "
                . "WHERE ouv.user_id = :user_id "
                . "AND s.Seminar_id NOT IN (:int_ma, :int_pb) "
                . "AND ouv.type = 'sem' "
                . "AND s.Seminar_id in "
                . "(SELECT su.Seminar_id FROM seminar_user as su WHERE su.user_id = :user_id) ORDER BY ouv.visitdate DESC");

        $statement->execute([':user_id' => $GLOBALS['user']->id, ':int_ma' => '111', ':int_pb' => '222']);
        $this->courses = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        //Get File-Folders of Intern Seminar MitarbeiterInnen (if configured)
        foreach($this->intranet_courses as $course){
            $config = IntranetSeminar::find([$course->id, $inst_id]);
            if ($config && $config->use_files){
                $this->filesCaptions[$course->id] = $config->files_caption;
                
                $db = DBManager::get();
                $stmt = $db->prepare("SELECT folder_id, name FROM folder WHERE seminar_id = :cid");
                $stmt->bindParam(":cid", $course->id);
                $stmt->execute();
                $sem_folder = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $folderwithfiles = array();

                foreach ($sem_folder as $folder){

                    $db = \DBManager::get();
                    $stmt = $db->prepare("SELECT * FROM `dokumente` WHERE `range_id` = :range_id
                        ORDER BY `name`");
                    $stmt->bindParam(":range_id", $folder['folder_id']);
                    $stmt->execute();
                    $response = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                    $files = array();

                    foreach ($response as $item) {
                        $files[] = $item;
                    }
                    $folderwithfiles[$folder['name']] = $files;

                }
                $this->folderwithfiles_array[$course->id] = $folderwithfiles;
            }
        }


        //get news of more connected seminars (if configured)
        $dispatcher = new StudipDispatcher();
        $controller = new NewsController($dispatcher);
        $response = $controller->relay('news/display/' . $sem_id_projektbereich);
        //$response = $controller->relay('news/display/9fc5dd6a84acf0ad76d2de71b473b341'); //localhost
        $this->projectnewstemplate = $GLOBALS['template_factory']->open('shared/string');
        $this->projectnewstemplate->content = $response->body;
        

        if (StudipNews::CountUnread() > 0) {
            $navigation = new Navigation('', PluginEngine::getLink($this, array(), 'read_all'));
            $navigation->setImage(Icon::create('refresh', 'clickable', ["title" => _('Alle als gelesen markieren')]));
            $icons[] = $navigation;
        }

        $this->projectnewstemplate->icons = $icons;
        
        

         //get upcoming courses (studip dates of configured category)
        $result = EventData::findBySQL("category_intern = '13' AND start > '" . time() . "' ORDER BY start ASC");
        $this->courses_upcoming = $result;
        
        
        
        $this->template = IntranetConfig::find($inst_id)->template;
        $this->render_action($this->template);

    }
    
    public function getNewsTemplateForSeminar($sem_id){
        //get intern news
        $dispatcher = new StudipDispatcher();
        $controller = new NewsController($dispatcher);
        $response = $controller->relay('news/display/' . $sem_id);
        //$response = $controller->relay('news/display/9fc5dd6a84acf0ad76d2de71b473b341'); //localhost
        $this->internnewstemplate = $GLOBALS['template_factory']->open('shared/string');
        $this->internnewstemplate->content = $response->body;
        
        if (StudipNews::CountUnread() > 0) {
            $navigation = new Navigation('', PluginEngine::getLink($this, array(), 'read_all'));
            $navigation->setImage(Icon::create('refresh', 'clickable', ["title" => _('Alle als gelesen markieren')]));
            $icons[] = $navigation;
        }

        $this->internnewstemplate->icons = $icons;
        return $this->internnewstemplate;
    }
    
    
    // customized #url_for for plugins
    public function url_for($to)
    {
        $args = func_get_args();

        # find params
        $params = array();
        if (is_array(end($args))) {
            $params = array_pop($args);
        }

        # urlencode all but the first argument
        $args = array_map('urlencode', $args);
        $args[0] = $to;

        return PluginEngine::getURL($this->dispatcher->plugin, $params, join('/', $args));
    }

}
