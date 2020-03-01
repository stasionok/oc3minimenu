<?php
/******************************************************
 * @package miniMenu module for Opencart 3.x
 * @link https://web-marshal.ru/minimenu-opencart-3-module/
 * @version 1.0
 * @author https://www.linkedin.com/in/stasionok/
 * @copyright Based on PavoThemes.com Pavo Menu module
 * @license GNU General Public License version 2
 *******************************************************/

/**
 * class ControllerExtensionModuleMiniMenu
 */
class ControllerExtensionModuleMinimenu extends Controller
{

    /**
     * @var array $error .
     */
    private $error = array();

    /**
     * @var string
     */
    private $module_name = 'minimenu';

    public $mdata;

    public $types = array();

    /*
     * @var int Module inuse id
     */
    private $module_id = 0;

    /**
     * Index Action
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('extension/module/minimenu');

        $this->language->load('extension/module/minimenu');

        $this->module_id = !empty($this->request->get['module_id']) ? $this->request->get['module_id'] : 0;
        $this->module_id = preg_replace('@[^\d]+@si', '', $this->module_id);
    }

    public function install()
    {
        $this->model_extension_module_minimenu->install();
    }

    public function index()
    {
        $this->document->setTitle(strip_tags($this->language->get('heading_title')));

        $this->load->model('tool/image');
        $this->load->model('setting/store');
        $this->load->model('setting/module');

        $this->document->addStyle('view/stylesheet/minimenu.css');
        $this->document->addStyle('view/javascript/jquery/jquery-ui/jquery-ui.min.css');
        $this->document->addScript('view/javascript/jquery/jquery-ui/jquery-ui.min.js');
        $this->document->addScript('view/javascript/summernote/summernote.js');
        $this->document->addStyle('view/javascript/summernote/summernote.css');
        $this->document->addScript('view/javascript/minimenu/jquery.nestable.js');

        $this->mdata['item_id'] = !empty($this->request->get['item_id']) ? $this->request->get['item_id'] : 0;

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            if (!$this->hasPermission()) {
                $this->error['warning'] = $this->language->get('error_permission');
            } else {
                $module = [
                    'name' => $this->request->post['name'],
                    'store_id' => $this->request->post['store_id'],
                    'status' => $this->request->post['status'],
                    'module_id' => $this->module_id,
                    'burger_text' => $this->request->post['burger_text'],
                ];

                if ($this->module_id > 0)
                    $this->model_setting_module->editModule($this->module_id, $module);
                else
                    $this->module_id = $this->model_extension_module_minimenu->addModule($this->module_name, $module);

                if (!empty($this->request->post['minimenu'])) {
                    $this->request->post['minimenu']['module_id'] = $this->module_id;
                    $this->mdata['item_id'] = $this->model_extension_module_minimenu->editData($this->request->post);
                }

                $this->session->data['success'] = $this->language->get('text_success');

                switch ($this->request->post['save_mode']) {
                    case 'delete-categories':
                        $this->model_extension_module_minimenu->deletecategories($this->request->post['store_id'], $this->module_id);
                        return $this->redirect($this->url->link('extension/module/minimenu', '&user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->module_id, true));
                        break;
                    case 'import-categories':
                        $this->model_extension_module_minimenu->importCategories($this->request->post['store_id'], $this->module_id);
                        return $this->redirect($this->url->link('extension/module/minimenu', '&user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->module_id, true));
                        break;
                    case  'save-new':
                        return $this->redirect($this->url->link('extension/module/minimenu', '&user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->module_id, true));
                        break;
                    case  'save-edit':
                        return $this->redirect($this->url->link('extension/module/minimenu', 'item_id=' . $this->mdata['item_id'] . '&user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->module_id, true));
                        break;
                    default:
                        return $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
                        break;
                }
            }
        }
        $base_params = $this->mdata['user_token'] = '&user_token=' . $this->session->data['user_token'];
        $base_params .= $this->module_id > 0 ? '&module_id=' . $this->module_id : '';

        $stores = $this->model_setting_store->getStores();
        foreach ($stores as &$store) {
            $url = $store['store_id'] > 0 ? '&store_id=' . $store['store_id'] : '';
            $store['option'] = $this->url->link('extension/extension/minimenu', $base_params . $url . '&type=module', true);
        }
        $this->mdata['stores'] = $stores;

        $store_id = 0;
        if (!empty($this->request->get['store_id'])) {
            $store_id = $this->request->get['store_id'];
            $base_params .= "&store_id=" . $store_id;
        }

        $this->mdata['basic_params'] = $base_params;
        $this->mdata['module_id'] = $this->module_id;
        $this->mdata['store_id'] = $store_id;
        $this->mdata['breadcrumbs'] = array();

        $this->mdata['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'], true),
            'separator' => false
        );

        $this->mdata['breadcrumbs'][] = array(
            'text' => $this->language->get('text_module'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true),
            'separator' => ' :: '
        );

        $this->mdata['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/minimenu', $base_params, true),
            'separator' => ' :: '
        );

        $this->mdata['action'] = $this->url->link('extension/module/minimenu', $base_params, true);
        $this->mdata['action_del'] = $this->url->link('extension/module/minimenu/delete', $base_params, true);
        $this->mdata['update_tree'] = $this->url->link('extension/module/minimenu/update', $base_params . '&root=0', true);
        $this->mdata['cancel'] = $this->url->link('marketplace/extension', $base_params . '&type=module', true);

        foreach (['action', 'action_del', 'update_tree', 'cancel'] as $act) {
            $this->mdata[$act] = str_replace('&amp;', '&', $this->mdata[$act]);
        }

        //get current language id
        $this->mdata['language_id'] = $this->config->get('config_language_id');

        $module = $this->model_setting_module->getModule($this->module_id);
        $this->mdata = array_merge($this->mdata, $module);

        $this->mdata['tree'] = $this->model_extension_module_minimenu->getTree(null, $store_id, $this->module_id, $this->mdata['item_id']);

        $this->mdata['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

        $this->prepareOutput();

        if (isset($this->error['warning'])) {
            $this->mdata['error_warning'] = $this->error['warning'];
        } else {
            $this->mdata['error_warning'] = '';
        }

        $this->template = 'extension/module/minimenu/minimenu';
        return $this->response->setOutput($this->render());
    }

    /**
     * Info Action to Get menu information by id
     */
    public function prepareOutput()
    {
        $this->load->model('catalog/product');
        $this->load->model('catalog/category');
        $this->load->model('catalog/manufacturer');
        $this->load->model('catalog/information');
        $this->load->model('localisation/language');
        $this->load->model('tool/image');
        $this->mdata['no_image'] = $this->model_tool_image->resize('no_image.jpg', 16, 16);
        $this->mdata['text_none'] = $this->language->get('text_none');
        $this->mdata['yesno'] = array('0' => $this->language->get('text_no'), '1' => $this->language->get('text_yes'));
        $this->mdata['languages'] = $this->model_localisation_language->getLanguages();
        $this->mdata['informations'] = $this->model_catalog_information->getInformations();

        $menu =  $this->model_extension_module_minimenu->getDefaults();
        if ($this->mdata['item_id'] > 0) {
            $menu = $this->model_extension_module_minimenu->getInfo($this->mdata['item_id']);
            if (isset($this->request->post['minimenu'])) $menu = array_merge($menu, $this->request->post['minimenu']);

            // build menu items
            $this->mdata['thumb'] = $this->model_tool_image->resize($menu['image'], 100, 100);
            // get item description
            $this->mdata['menu_description'] = array();
            $descriptions = $this->model_extension_module_minimenu->getMenuDescription($this->mdata['item_id']);
            foreach ($descriptions as $d) {
                $this->mdata['menu_description'][$d['language_id']] = $d;
            }
        }

        foreach ($this->mdata['languages'] as $language) {
            if (empty($this->mdata['menu_description'][$language['language_id']]))
                $this->mdata['menu_description'][$language['language_id']] = [
                    'title' => '',
                    'description' => ''
                ];
        }

        if (!empty($menu['item'])) {
            switch ($menu['type']) {
                case 'category':
                    $category = $this->model_catalog_category->getCategory($menu['item']);
                    $menu['minimenu_category'] = isset($category['name']) ? $category['name'] : "";
                    break;
                case 'product':
                    $product = $this->model_catalog_product->getProduct($menu['item']);
                    $menu['minimenu_product'] = isset($product['name']) ? $product['name'] : "";
                    break;
                case 'information':
                    $menu['minimenu_information'] = $menu['item'];
                    break;
                case 'manufacturer':
                    $manufacturer = $this->model_catalog_manufacturer->getManufacturer($menu['item']);
                    $menu['minimenu_manufacturer'] = isset($manufacturer['name']) ? $manufacturer['name'] : "";
                    break;
            }
        }

        $this->mdata['menu'] = $menu;


        $this->mdata['text_edit_menu'] = $this->language->get('text_edit_menu');
        $this->mdata['text_create_new'] = $this->language->get('text_create_new');

        $this->mdata['minimenutypes'] = [
            'url' => $this->language->get('text_url'),
            'category' => $this->language->get('text_category'),
            'information' => $this->language->get('text_information'),
            'product' => $this->language->get('text_product'),
            'manufacturer' => $this->language->get('text_manufacturer'),
            'html' => $this->language->get('text_html')
        ];
    }

    public function render()
    {
        $this->mdata['header'] = $this->load->controller('common/header');
        $this->mdata['column_left'] = $this->load->controller('common/column_left');
        $this->mdata['footer'] = $this->load->controller('common/footer');

        return $this->load->view($this->template, $this->mdata);
    }

    public function redirect($url)
    {
        return $this->response->redirect($url);
    }

    protected function hasPermission()
    {
        return $this->user->hasPermission('modify', 'extension/module/' . $this->module_name);
    }

    /**
     * Delete Menu Action
     */
    public function delete()
    {
        if (!$this->hasPermission()) die($this->language->get('error_permission'));

        $store_id = !empty($this->request->get['store_id']) ? (int)$this->request->get['store_id'] : 0;
        $module_id = !empty($this->request->get['module_id']) ? (int)$this->request->get['module_id'] : 0;
        $base_params = 'user_token=' . $this->session->data['user_token'];
        $base_params .= $store_id > 0 ? '&store_id=' . $store_id : '';
        $base_params .= $module_id > 0 ? '&module_id=' . $module_id : '';
        $base_params = str_replace('&amp;', '&', $base_params);

        if (isset($this->request->get['item_id'])) {
            $this->model_extension_module_minimenu->delete((int)$this->request->get['item_id'], $store_id, $module_id);
        }

        return $this->redirect($this->url->link('extension/module/minimenu', $base_params, true));
    }

    /**
     * Update menu order
     */
    public function update()
    {
        if (!$this->hasPermission()) die($this->language->get('error_permission'));

        $this->model_extension_module_minimenu->massUpdate($this->request->post['list'], $this->request->get['root']);
    }

    /**
     * Check Validation
     */
    protected function validate()
    {
        if (!$this->hasPermission()) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!isset($this->request->post['save_mode']) OR
            !in_array($this->request->post['save_mode'], ['save', 'save-new', 'save-edit', 'import-categories', 'delete-categories']))
            $this->error['warning'] = $this->language->get('error_incorrect_submit');

        $this->request->post['burger_text'] = !empty($this->request->post['burger_text']) ?
            $this->request->post['burger_text'] :
            htmlentities('<i class="fa fa-bars"></i>');


        $mm = $this->request->post['minimenu'];
        $not_edit = 'url' == $mm['type'] && empty($mm['url']) && !$this->mdata['item_id'] && empty($mm['title']);

        if ($not_edit) {
            unset($this->request->post['minimenu']);
        } else {
            $minimenu_fields = $this->model_extension_module_minimenu->getDefaults();

            $this->request->post['minimenu'] = array_merge($minimenu_fields, $this->request->post['minimenu']);
            $this->request->post['minimenu'] = array_intersect_key($this->request->post['minimenu'], $minimenu_fields);

            $languageId = (int)$this->config->get('config_language_id');
            $d = isset($this->request->post['minimenu_description'][$languageId]['title']) ? $this->request->post['minimenu_description'][$languageId]['title'] : "";
            if (empty($d)) {
                $this->error['warning'] = $this->language->get('error_missing_title');
            }

            if (!isset($this->request->post['status'])) $this->request->post['status'] = 0;
            if (!isset($this->request->post['store_id'])) $this->request->post['store_id'] = 0;

            foreach ($this->request->post['minimenu_description'] as $key => $value) {
                if (empty($value['title'])) {
                    $this->request->post['minimenu_description'][$key]['title'] = $d;
                }
                foreach ($value as $name => $val)
                    if (!in_array($name, ['title', 'description', 'language_id', 'item_id']))
                        unset($this->request->post['minimenu_description'][$key][$name]);
            }
        }

        if (!$this->error) return true;

        return false;
    }
}