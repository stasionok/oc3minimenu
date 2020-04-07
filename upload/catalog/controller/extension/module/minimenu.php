<?php
/**
 * @package miniMenu module for Opencart 3.x
 * @version 1.0
 * @author https://www.linkedin.com/in/stasionok/
 * @copyright    Based on PavoThemes.com Pavo Menu module
 * @license        GNU General Public License version 2
 */

class ControllerExtensionModuleMinimenu extends Controller
{

    public $data;
    static $MINIMENU;

    public function index($setting)
    {
        $this->load->model('extension/module/minimenu');

        $theme_dir = $this->config->get('theme_' . $this->config->get('config_theme') . '_directory');
        if (file_exists(DIR_TEMPLATE . $theme_dir . '/stylesheet/minimenu/minimenu.css')) {
            $this->document->addStyle('catalog/view/theme/' . $theme_dir . '/stylesheet/minimenu/minimenu.css');
        } else {
            $this->document->addStyle('catalog/view/theme/default/stylesheet/minimenu/minimenu.css');
        }

        $this->data['module_id'] = $setting['module_id'];
        $this->data['burger_text'] = html_entity_decode($setting['burger_text']);

        if (!isset(self::$MINIMENU[$setting['module_id']])) {
            $current = [
                'host' => $this->request->server['SERVER_NAME'],
                'uri' => $this->request->server['REQUEST_URI']
            ];
            self::$MINIMENU[$setting['module_id']] = $this->model_extension_module_minimenu->getTree($parent = 0, $setting['store_id'], $setting['module_id'], $current);
        }
        $this->data['treemenu'] = self::$MINIMENU[$setting['module_id']];

        $template = 'extension/module/minimenu';

        return $this->load->view($template, $this->data);
    }
}

?>