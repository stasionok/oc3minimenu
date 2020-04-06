<?php
/******************************************************
 * @package miniMenu module for Opencart 3.x
 * @link https://web-marshal.ru/minimenu-opencart-3-module/
 * @version 1.0
 * @author https://www.linkedin.com/in/stasionok/
 * @copyright Based on PavoThemes.com Pavo Menu module
 * @license GNU General Public License version 2
 *******************************************************/

class ModelExtensionModuleMinimenu extends Model
{
    private $output = false;
    private $children;
    private $shopUrl;

    /**
     * @param null $id
     * @param int $store_id
     * @param int $module_id
     * @return array
     */
    public function getChild($id = null, $store_id = 0, $module_id = 0)
    {
        $sql = 'SELECT m.*, md.title, md.description 
                FROM ' . DB_PREFIX . 'minimenu m 
                LEFT JOIN ' . DB_PREFIX . 'minimenu_description md ON m.item_id=md.item_id 
                      AND language_id = ' . (int)$this->config->get('config_language_id') . '
                WHERE published = 1 
                  AND store_id = ' . (int)$store_id;
        if (isset($id))
            $sql .= ' AND parent_id=' . (int)$id;
        $sql .= ' AND module_id=' . (int)$module_id . '
                ORDER BY `position`  ';
        $query = $this->db->query($sql);
        $this->children = array();
        foreach ($query->rows as $child) {
            $this->children[$child['parent_id']][] = $child;
        }

        return $this->children;
    }

    /**
     * @param $id
     * @return bool
     */
    public function hasChild($id)
    {
        return isset($this->children[$id]);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getNodes($id)
    {
        return $this->children[$id];
    }

    /**
     * @param int $parent
     * @param int $store_id
     * @param int $module_id
     * @return string
     */
    public function getTree($parent = 0, $store_id = 0, $module_id = 0)
    {
        $this->getChild(null, $store_id, $module_id);

        if ($this->request->server['HTTPS']) {
            $this->shopUrl = $this->config->get('config_ssl');
        } else {
            $this->shopUrl = $this->config->get('config_url');
        }

        return $this->output = $this->makeMenu($parent);
    }

    /**
     * Build recursive menu HTML
     * @param $parent
     * @param int $level
     * @return string
     */
    private function makeMenu($parent, $level = 0)
    {
        $output = '';
        if ($this->hasChild($parent)) {
            $data = $this->getNodes($parent);
            $output = $level == 0 ?
                '<ul class="nav navbar-nav">' :
                '<ul class="dropdown-menu level' . $level . '">';
            foreach ($data as $menu) {
                $menu['menu_class'] = trim($menu['menu_class']);
                $menu['menu_class'] = !empty($menu['menu_class']) ? ' ' . $menu['menu_class'] : '';
                if ($this->hasChild($menu['item_id'])) {
                    $output .= '<li class="menu-item dropdown dropdown-submenu' . $menu['menu_class'] . '">';
                    $output .= '<a class="dropdown-toggle" href="' . $this->getLink($menu) . '" data-toggle="dropdown">';
                    $output .= $this->menuItemContent($menu);
                    $output .= '<b class="caret"></b>';
                    $output .= '</a>';
                    $output .= $this->makeMenu($menu['item_id'], $level + 1);
                    $output .= '</li>';
                } elseif ($menu['type'] == 'html') {
                    $output .= '<li class="menu-content content-html menu-item' . $menu['menu_class'] . '">';
                    $output .= $this->menuItemContent($menu);
                    $output .= html_entity_decode($parent['content_text']);
                    $output .= '</li>';
                } else {
                    $url = $this->getLink($menu);
                    $active = ($url == $this->activeUrl['uri'] OR
                        $url == $this->activeUrl['host'] . $this->activeUrl['uri']) ? ' active' : '';
                    $output .= '<li class="menu-item' . $menu['menu_class'] . $active . '">';
                    $output .= '<a href="' . $url . '">';
                    $output .= $this->menuItemContent($menu);
                    $output .= '</a></li>';
                }
            }
            $output .= '</ul>';
        }

        return $output;
    }

    /**
     * Generate menuitem extra content if avail.
     *
     * @param $menu
     * @return string
     */
    private function menuItemContent($menu)
    {
        $this->load->language("extension/module/minimenu");

        $output = '';

        if (!empty($menu['image'])) {
            $output .= '<img class="menu-image" src="' . $this->shopUrl . 'image/' . $menu['image'] . '" alt="' . (!empty($menu['description']) ? $menu['description'] : $menu['title']) . '" title="' . $menu['title'] . '" />';
        }

        if (!empty($menu['icon'])) {
            $output .= '<span class="' . $menu['icon'] . '"></span>';
        }

        if ($menu['show_title']) {
            $output .= '<span class="menu-title">' . $menu['title'] . "</span>";
        }

        if (!empty($menu['description'])) {
            $output .= '<span class="menu-desc">' . $menu['description'] . "</span>";
        }

        if (!empty($menu['badges'])) {
            $output .= '<span class="badges ' . $menu['badges'] . '">' . $this->language->get($menu['badges']) . '</span>';
        }


        return $output;
    }

    public function getParentCategory($id_child)
    {
        $sql = "SELECT parent_id 
                FROM " . DB_PREFIX . "category 
                WHERE category_id = {$id_child}";
        $result = $this->db->query($sql);

        return $result->row;
    }

    /**
     * @param $menu array
     * @return string
     */
    public function getLink($menu)
    {
        $id = (int)$menu['item'];
        switch ($menu['type']) {
            case 'category':
                $pid = $id;
                while ($parent = $this->getParentCategory((int)$pid) && !empty($parent['parent_id'])) {
                    $id = $parent['parent_id'] . '_' . $id;
                    $pid = $parent['parent_id'];
                }
                return $this->url->link('product/category', 'path=' . $id, 'SSL');
            case 'product':
                return $this->url->link('product/product', 'product_id=' . $id, 'SSL');
            case 'information':
                return $this->url->link('information/information', 'information_id=' . $id, 'SSL');
            case 'manufacturer':
                return $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $id, 'SSL');
            case 'url':
                return $menu['url'];
            default:
                return '#';
        }
    }
}
