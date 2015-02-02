<?php

class Create_Images_Generator extends catimgen {

    public $image_list = array();
    public $image_list_empty = array();
    public $image_list_no_product = array();

    public function __construct() {
        parent::__construct();
    }

    public function List_images() {
        if (is_dir(_PS_CAT_IMG_DIR_)) {
            $handle = opendir(_PS_CAT_IMG_DIR_);
        } else {
            return 'No category images directory';
        }
        while (($file = readdir($handle)) !== false) {
            if (strpos((string)$file, "-") === FALSE && strpos((string)$file, "_") === FALSE && strpos((string)$file, ".jpg") == TRUE){
                if((int)str_replace(".jpg", "", $file) > 0){
                    $this->image_list[$file] = (int)str_replace(".jpg", "", $file);                    
                }    
            }
        }
    }

    public function Create_list($id_shop = 0, $id_lang = 0) {

        if ($id_shop == 0) {
            $id_shop = Context::getContext()->shop->id;
        }
        if ($id_lang == 0) {
            $id_lang = Context::getContext()->language->id;
        }

        $sql = "SELECT DISTINCT * 
                FROM    `ps_category_lang` 
                WHERE   `id_shop` = {$id_shop}
                AND     `id_lang` = {$id_lang}";
//        echo $sql;
        $return = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
//        echo count($return);
        foreach ($return as $index => $value) {            
            if (!in_array((int) $value['id_category'], $this->image_list)) {    
//                echo "FOR CAT {$value['id_category']}<br />";
                $product = $this->Get_category_product((int) $value['id_category']);                
                if (($product != null) && (count($product) > 0)) {
                    $this->image_list_empty[(int) $value['id_category']] = (array)$product; // seznam kategorii bez obrazku ale s produktem, pak dohledat narazene
                } else {
                    $this->image_list_no_product[] = (int) $value['id_category'];// seznam kategorii bez obrazku a bez produktu
                    //$this->associate_product_image_for_parent_categories((int) $value['id_category'], (array)$product);
                    
                }
                //$this->Repair_no_products_category_image();
            }
            else{
//                echo "FOR CAT {$value['id_category']}<br />";
            }
        }
    }
    
    public function Repair_no_products_category_image() {
        
        foreach($this->image_list as $id_category){
            $this->associate_product_image_for_parent_categories((int) $id_category);
        }
    }
    
    /* Pro nadrazene kategorie priradi obrazek produktu z ditete ktery je ma*/
    public function associate_product_image_for_parent_categories($id_category){   
       
        if($id_category > 0 ){
            $id_parent = $this->Get_parent_of_category($id_category);
            if($id_parent > 0){
                echo "COPY" . _PS_CAT_IMG_DIR_ . "" . $id_category . '.jpg',_PS_CAT_IMG_DIR_ . "" . $id_parent . '.jpg';
                if(copy(_PS_CAT_IMG_DIR_ . "" . $id_category . '.jpg',_PS_CAT_IMG_DIR_ . "" . $id_parent . '.jpg')){
                    print "Image for id parent {$id_parent}<hr>";
                };
            }
            if($this->Get_parent_of_category($id_parent) > 0){
                $this->associate_product_image_for_parent_categories($id_parent);
            }
            else{
                return;
            }
            
        }        
    }
    
    /* get parent of category */
    public function Get_parent_of_category($id_category, $id_shop = 0){
        if ($id_shop == 0) {
            $id_shop = Context::getContext()->shop->id;
        }
        if((int)$id_category > 0){
            $sql = "SELECT DISTINCT ps_category.id_parent, ps_category.is_root_category,ps_category_shop.id_shop
                    FROM ps_category
                    INNER JOIN ps_category_shop ON ps_category_shop.id_category = ps_category.id_category
                    AND ps_category_shop.id_shop ={$id_shop} WHERE ps_category.id_category = {$id_category};";
                    
            $return = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
            print_r($return);
            echo "<hr>";
            if(count($return) > 0){
                foreach($return as $key => $value){

                        if($value['is_root_category'] == 0){
                            print_r($value['id_parent']);
                            return $value['id_parent'];
                        }
                        else{
                            return null;
                        }
                    }
                
            }
            else{
                return null;
            }
        }        
        else{ return null; }
    }
    

    /* return category => array(int id_product => int id_image, ... ) co maji nejaky image image */
    public function Get_category_product($id_category = 0, $id_shop = 0) {
        if ($id_shop == 0) {
            $id_shop = Context::getContext()->shop->id;
        }
        if ((int) $id_category > 0) {
            $sql = "SELECT DISTINCT cp.id_product, i.id_image FROM " . _DB_PREFIX_ . "category_product cp";
            $sql .= " INNER JOIN " . _DB_PREFIX_ . "image i ON (i.id_product = cp.id_product)";
            $sql .= " INNER JOIN " . _DB_PREFIX_ . "image_shop ims ON (i.id_image = ims.id_image) AND ims.id_shop = " . $id_shop;
            $sql .= " LEFT JOIN " . _DB_PREFIX_ . "product_shop ps ON (ps.id_product = cp.id_product) AND ps.id_shop = " . $id_shop;
            $sql .= " LEFT JOIN " . _DB_PREFIX_ . "stock_available sa ON (sa.id_product = cp.id_product) AND sa.id_shop = " . $id_shop;
            $sql .= " WHERE cp.id_category = {$id_category} ORDER BY cp.position ASC,sa.quantity DESC;";
            $return = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

            /* test zda existuje pro id productu - id_image skutecne obrazek a ulozi cestu k obrazku */
            $accepted_images = array();
            foreach ($return as $index => $value) {
                (string) $image_path = $this->Create_product_image_path($value["id_image"]);
                if (ImageManagerCore::isRealImage($image_path)) {
                    $accepted_images[$value["id_product"]] = $value;
                    $accepted_images[$value["id_product"]]['path'] = $image_path;
                    //$accepted_images[$value["id_product"]]['size'] = filesize($image_path);
                }
            }
            return $accepted_images;
        } else {
            return null;
        }
    }

    /* kategorie pro ktere nebyli produkty nalezeny => kategorie nadrazene koncove. Produkt je vetsinou zadan do posledni kategorie */

    

    public function Create_category_image_path($image_id) {
        return _PS_CAT_IMG_DIR_ . "{$this->Create_image_path($image_id)}{$image_id}.jpg";
    }

    public function Create_product_image_path($image_id) {
        return _PS_PROD_IMG_DIR_ . "{$this->Create_image_path($image_id)}{$image_id}.jpg";
    }

    /* from int create path/path/ and return */

    public function Create_image_path($image_id) {
        $path = str_split($image_id);
        $return = "";
        foreach ($path AS $part_of_path) {
            $return .= $part_of_path . "/";
        }
        return $return;
    }

    public function Create_image_folder($des, $id_image) {
        $array_folder = str_split($id_image);
        $created_dir_path = "";
        foreach ($array_folder as $folder) {
            $created_dir_path .= $folder . "/";
            if (!file_exists($des . $created_dir_path))
                mkdir($des . $created_dir_path, 0777);
            chmod($des . $created_dir_path, 0777);
        }
        return true;
    }

    public function Create_image_thumbs() {
       //$image_type_list = ImageType::getImagesTypes('categories');
        $check_it = array();
        foreach ($this->image_list_empty as $id_category => $product) {
            $check_it[$id_category] = false;
            foreach($product as $prod_image){     
                if($check_it[$id_category] == false){
                    if(copy($prod_image['path'], _PS_CAT_IMG_DIR_ . "" . $id_category . '.jpg') === true){
                        $check_it[$id_category] = true;
                        //sleep(1);
                        //echo "was coppy" . $prod_image['path'], _PS_CAT_IMG_DIR_ . "" . $id_category . '.jpg<hr>';
                    }
                }
            }
            //copy(array_pop($product)['path'], _PS_CAT_IMG_DIR_ . "" . $id_category . '.jpg');
        }
    }

    public function recursive_array_search($needle, $haystack) {
        foreach ($haystack as $key => $value) {
            $current_key = $key;
            if ($needle === $value OR ( is_array($value) && recursive_array_search($needle, $value) !== false)) {
                return $current_key;
            }
        }
        return false;
    }

}
