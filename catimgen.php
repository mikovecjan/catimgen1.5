<?php

include_once 'category_images.php';
class catimgen extends Module {

    public $text = '';
    static public $hodnota = '';
    static public $hodnota_info = '';
    public static $category_imager = null;
    public static $missing_images = array();
    public $error = array();
    public static $image_generator;

    public function __construct() {
        $this->name = 'catimgen';
        $this->tab = 'front_office_features';
        $this->version = 1.0;
        $this->author = 'MIKODEV - Mikovec Jan';
        $this->need_instance = 1;
        $this->is_configurable = 1;
        $this->displayName = $this->l('Catimgen - category image generator');
        $this->description = $this->l('Use for generate you category images form products, without thumbs ! For thumbs use native prestashop tools.');
        $this->path = defined('__DIR__') ? __DIR__ : dirname(__FILE__);
        parent::__construct();
        return true;
    }

    public function install() {
        if (!parent::install())
            return false;
        return true;
    }

    public function prepareProccess(){
        
        if(!self::$category_imager){
            self::$category_imager = new Create_Images_Generator();
            self::$category_imager->List_images();
            self::$category_imager->Create_list();            
        }
        else{
            $errors[] = 'Can not create Create_Images object';
        }
    }
    
    public function postProcess() {
        global $currentIndex;
        $errors = array();
        $this->prepareProccess();
        $output = '';
        if (Tools::isSubmit('submit_category_images')) {
            if (Tools::isSubmit('generate_all')) { // vytvori seznam jiz existujicich obrazku kategorii
                    if((array)count(self::$category_imager->Create_list()) > 0 ){
                        self::$category_imager->Create_image_thumbs();  
                        self::$category_imager->Repair_no_products_category_image();
                    }
            }
        }
        if ($errors)
            echo $this->displayError($errors);
    }
    
    public function getContent() {
        global $protocol_content;
        $this->postProcess();
        $output = '';
        $counter_empty = count(self::$category_imager->image_list_empty);
        $counter_empty_no_product = count(self::$category_imager->image_list_no_product);
        $output = "<h2>{$this->l('Category for repair')} ({$counter_empty} / {$counter_empty_no_product})</h2>";
        $output .= "<form action=\"{$_SERVER['REQUEST_URI']}\" method=\"post\" enctype=\"multipart/form-data\">";
        $output .= "<fieldset><legend>{$this->l('Category images creator')}</legend>";
        $output .= "<fieldset><legend>{$this->l('Create all images')}</legend>"
                . "<label for=\"generate_all\">{$this->l('Create all images')}</label>"
                . "<input type=\"checkbox\" name=\"generate_all\" /></legend></fieldset>";
        $output .= "<input class=\"button\" type=\"submit\" name=\"submit_category_images\" value=Working and Generate\" />";
        $output .= "</fieldset></form>";
        return $output;
    }

}
