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
    /**
     * @var array $children as collections of children menus
     *
     * @accesss protected
     */
    protected $children = array();

    public function getDefaults()
    {
        return [
            'item_id' => '',
            'module_id' => 0,
            'store_id' => 0,
            'parent_id' => 0,
            'position' => 99,
            'type' => 'url',
            'item' => '',
            'url' => '',
            'content_text' => '',
            'published' => 1,
            'show_title' => 1,
            'image' => '',
            'icon' => '',
            'menu_class' => '',
            'badges' => '',
        ];
    }

    /**
     * Automatic checking installation to creating tables and data sample, configuration of modules.
     */
    public function install()
    {
        $sql = " SHOW TABLES LIKE '" . DB_PREFIX . "minimenu'";
        $query = $this->db->query($sql);

        if (count($query->rows) <= 0) {
            $sql = array();
            $sql[] = "
				CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "minimenu` (
				  `item_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `module_id`  int(11) NOT NULL,
				  `store_id` smallint(5) unsigned NOT NULL DEFAULT '0',
				  `parent_id` int(11) NOT NULL DEFAULT '0',
				  `position` int(11) unsigned NOT NULL DEFAULT '99',
				  `type` varchar(255) NOT NULL DEFAULT 'url',
				  `item` varchar(255) DEFAULT NULL,
				  `url` varchar(255) DEFAULT NULL,
				  `content_text` text,
				  `published` smallint(6) NOT NULL DEFAULT '1',
				  `show_title` smallint(6) NOT NULL DEFAULT '1',
				  `image` varchar(255) NOT NULL DEFAULT '',
				  `icon` varchar(255) NOT NULL DEFAULT '',
				  `menu_class` varchar(25) DEFAULT NULL,
				  `badges` text DEFAULT '',
				  PRIMARY KEY (`item_id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
			";
            $sql[] = '
                CREATE TABLE IF NOT EXISTS `' . DB_PREFIX . 'minimenu_description` (
                  `item_id` int(11) NOT NULL,
                  `language_id` int(11) NOT NULL,
                  `title` varchar(255) NOT NULL,
                  `description` text NOT NULL,
                  PRIMARY KEY (`item_id`,`language_id`),
                  KEY `name` (`title`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
			';

            foreach ($sql as $q) {
                $this->db->query($q);
            }
        }
    }

    public function addModule($code, $data)
    {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "module` SET 
        `name` = '" . $this->db->escape($data['name']) . "', 
        `code` = '" . $this->db->escape($code) . "', 
        `setting` = '" . $this->db->escape(json_encode($data)) . "'");
        return $this->db->getLastId();
    }

    /**
     * Edit Or Create new children
     * @param $data
     * @return
     */
    public function editData($data)
    {
        if ($data["minimenu"]) {
            if (!empty($data['minimenu']['item_id'])) {
                $sql = [];
                foreach ($data["minimenu"] as $key => $value)
                    if (!in_array($key, ['item_id', 'position', 'parent_id']))
                        $sql[] = "`" . $key . "`='" . $this->db->escape($value) . "'";
                $sql = 'UPDATE ' . DB_PREFIX . 'minimenu SET ' . implode(",", $sql) . ' WHERE item_id=' . (int)$data['minimenu']['item_id'];

                $this->db->query($sql);
            } else {
                $data['minimenu']['position'] = 99;
                $keys = array();
                $vals = array();
                foreach ($data["minimenu"] as $key => $value) {
                    $keys[] = $key;
                    $vals[] = $this->db->escape($value);
                }

                $sql = "INSERT INTO " . DB_PREFIX . "minimenu ( `" . implode("`,`", $keys) . "`) VALUES ('" . implode("','", $vals) . "')";
                $this->db->query($sql);
                $data['minimenu']['item_id'] = $this->db->getLastId();
            }
        }

        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();

        if (isset($data["minimenu_description"])) {
            $sql = " DELETE FROM " . DB_PREFIX . "minimenu_description WHERE item_id = " . (int)$data["minimenu"]['item_id'];
            $this->db->query($sql);

            foreach ($languages as $language) {
                $sql = "INSERT INTO " . DB_PREFIX . "minimenu_description(`language_id`, `item_id`,`title`,`description`)
							VALUES(" . $language['language_id'] . ",'" . $data['minimenu']['item_id'] . "','" . $this->db->escape($data["minimenu_description"][$language['language_id']]['title']) . "','"
                    . $this->db->escape($data["minimenu_description"][$language['language_id']]['description']) . "') ";
                $this->db->query($sql);
            }
        }
        return $data['minimenu']['item_id'];
    }

    /**
     * Render Tree Menu by ID
     * @param null $id
     * @param int $store_id
     * @param int $module_id
     * @param int $selected
     * @return string|void
     */
    public function getTree($id = null, $store_id = 0, $module_id = 0, $selected = 0)
    {
        $this->getChild($id, $store_id, $module_id);

        return $this->genTree(0, 1, $module_id, $store_id, $selected);
    }

    /**
     * get get all  Menu children by Id
     * @param int $id as parentID
     * @param int $store_id
     * @param int $module_id
     * @return
     */
    public function getChild($id = 0, $store_id = 0, $module_id = 0)
    {
        $sql = 'SELECT m.*, md.title, md.description 
                FROM ' . DB_PREFIX . 'minimenu m 
                LEFT JOIN ' . DB_PREFIX . 'minimenu_description md ON m.item_id=md.item_id 
                      AND language_id=' . (int)$this->config->get('config_language_id') . '
                WHERE store_id=' . (int)$store_id;
        if (isset($id))
            $sql .= ' AND parent_id=' . (int)$id;
        $sql .= ' AND module_id=' . (int)$module_id . '
                ORDER BY position';

        $query = $this->db->query($sql);

        $this->children = array();
        foreach ($query->rows as $child) {
            $this->children[$child['parent_id']][] = $child;
        }

        return $this->children;
    }

    /**
     * @param $parent
     * @param $level
     * @param int $module_id
     * @param int $store_id
     * @param int $selected
     * @return string|void
     */
    public function genTree($parent, $level, $module_id = 0, $store_id = 0, $selected = 0)
    {
        if ($this->hasChild($parent)) {
            $data = $this->getNodes($parent);
            $t = $level == 1 ? " sortable" : "";
            $output = '<ol class="level' . $level . $t . '">';

            $base_params = '&user_token=' . $this->session->data['user_token'];
            $base_params .= $store_id > 0 ? '&store_id=' . $store_id : '';
            $base_params .= $module_id > 0 ? '&module_id=' . $module_id : '';
            $base_params = str_replace('&apm;', '&', $base_params);

            $this->language->load('extension/module/minimenu');
            $delete_text = $this->language->get('text_delete');
            $edit_text = $this->language->get('text_edit');

            foreach ($data as $menu) {
                $url = $this->url->link('extension/module/minimenu', 'item_id=' . $menu['item_id'] . $base_params, true);
                $cls = $menu['item_id'] == $selected ? 'class="list-menu-item active mjs-nestedSortable-expanded"' : 'class="list-menu-item mjs-nestedSortable-expanded"';
                $item_title = !empty($menu['title']) ? $menu['title'] : ' (ID:' . $menu['item_id'] . ')';
                $output .= '<li id="list_' . $menu['item_id'] . '" ' . $cls . '>
				<div>
				    <span class="disclose"><span></span></span>' .
                    $item_title . '
                    <a class="quickedit" rel="id_' . $menu['item_id'] . '" href="' . $url . '" title="' . $edit_text . '">E</a>
                    <a class="quickdel" rel="id_' . $menu['item_id'] . '" href="#" title="' . $delete_text . '">D</a>
                </div>';
                $output .= $this->genTree($menu['item_id'], $level + 1, $module_id, $store_id, $selected);
                $output .= '</li>';
            }
            $output .= '</ol>';
            return $output;
        }
        return;
    }

    /**
     * Get menu information by id
     * @param int $id
     * @return array
     */
    public function getInfo($id)
    {
        $sql = "SELECT m.*, md.title, md.description 
                FROM " . DB_PREFIX . "minimenu m 
                LEFT JOIN " . DB_PREFIX . "minimenu_description md ON m.item_id = md.item_id 
                      AND language_id = " . (int)$this->config->get('config_language_id') . "
                WHERE m.item_id = " . (int)$id;

        $query = $this->db->query($sql);

        if ($query->num_rows > 0) return $query->row;

        return $this->getDefaults();
    }

    /**
     * get menu description by id
     * @param $id
     * @return array
     */
    public function getMenuDescription($id)
    {
        $sql = 'SELECT * FROM ' . DB_PREFIX . "minimenu_description WHERE item_id=" . $id;
        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * whethere parent has menu childrens
     * @param $id
     * @return bool
     */
    public function hasChild($id)
    {
        return isset($this->children[$id]);
    }

    /**
     * get collection of menu childrens by parent ID.
     * @param $id
     * @return mixed
     */
    public function getNodes($id)
    {
        return $this->children[$id];
    }

    /**
     * delete menu data by id
     * @param $id
     * @param $store_id
     * @param $module_id
     */
    public function delete($id, $store_id, $module_id)
    {
        $this->getChild(null, $store_id, $module_id);

        $this->recursiveDelete($id);
    }

    /**
     * recursive delete tree
     * @param $parent_id
     */
    public function recursiveDelete($parent_id)
    {
        $sql = " DELETE FROM " . DB_PREFIX . "minimenu_description WHERE item_id=" . (int)$parent_id;
        $this->db->query($sql);
        $sql = " DELETE FROM " . DB_PREFIX . "minimenu WHERE item_id=" . (int)$parent_id;
        $this->db->query($sql);

        if ($this->hasChild($parent_id)) {
            $data = $this->getNodes($parent_id);
            foreach ($data as $menu) {
                $this->recursiveDelete($menu['item_id']);
            }
        }
    }

    /**
     * Mass Update Data for list of childrens by prent IDs
     * @param $data
     * @param $root
     */
    public function massUpdate($data, $root)
    {
        $child = array();
        foreach ($data as $id => $parent_id) {
            if ($parent_id <= 0) {
                $parent_id = $root;
            }
            $child[$parent_id][] = $id;
        }

        foreach ($child as $parent_id => $menus) {
            $i = 1;
            foreach ($menus as $menu_id) {
                $sql = " UPDATE  " . DB_PREFIX . "minimenu SET parent_id=" . (int)$parent_id . ', position=' . $i . ' WHERE item_id=' . (int)$menu_id;
                $this->db->query($sql);
                $i++;
            }
        }
    }

    public function checkExitItemMenu($category, $store_id)
    {
        $query = $this->db->query("SELECT item_id FROM " . DB_PREFIX . "minimenu 
            WHERE store_id = " . $store_id . " 
              AND `type`='category' 
              AND item=" . $category['category_id']);
        return $query->num_rows;
    }

    public function deletecategories($store_id, $module_id)
    {
        $sql = "SELECT item_id FROM " . DB_PREFIX . "minimenu 
                WHERE store_id = " . $store_id . " 
                  AND module_id = " . $module_id . "
                  AND type = 'category'";
        $query = $this->db->query($sql);
        if ($query->num_rows) {
            foreach ($query->rows as $row) {
                $this->db->query("DELETE FROM " . DB_PREFIX . "minimenu_description WHERE item_id = " . $row['item_id']);
            }
        }
        $sql = "DELETE FROM " . DB_PREFIX . "minimenu 
                WHERE store_id = " . $store_id . " 
                AND module_id = " . $module_id . "
                AND type = 'category'";
        $this->db->query($sql);
    }

    /**
     * @param int $store_id
     * @param $module_id
     * @return bool
     */
    public function importCategories($store_id = 0, $module_id = 0)
    {
        $sql = "SELECT cd.`name`,c.* FROM " . DB_PREFIX . "category c
				LEFT JOIN " . DB_PREFIX . "category_description cd ON c.category_id = cd.category_id
				WHERE  cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
				ORDER BY parent_id";
        $query = $this->db->query($sql);
        if (!$query->num_rows) return true;

        $this->load->model('catalog/category');
        foreach ($query->rows as &$category) {
            $category['language'] = $this->model_catalog_category->getCategoryDescriptions($category['category_id']);

            if ($this->checkExitItemMenu($category, $store_id) == 0) {
                $minimenu_parent_id = 0;
                if ((int)$category['parent_id'] > 0) {
                    $query1 = $this->db->query("SELECT item_id FROM " . DB_PREFIX . "minimenu WHERE store_id = " . $store_id . " AND `type`='category' AND item='" . $category['parent_id'] . "'");
                    if ($query1->num_rows) {
                        $minimenu_parent_id = (int)$query1->row['item_id'];
                    }
                }

                $this->insertCategory($category, $minimenu_parent_id, $store_id, $module_id);
            }
        }
        return true;
    }

    /**
     * @param array $category
     * @param int $minimenu_parent_id
     * @param int $store_id
     * @param int $module_id
     */
    public function insertCategory($category = array(), $minimenu_parent_id = 0, $store_id = 0, $module_id = 0)
    {
        $this->load->model('localisation/language');

        $data = array_merge($this->getDefaults(), [
            'item' => $category['category_id'],
            'parent_id' => $minimenu_parent_id,
            'type' => 'category',
            'store_id' => $store_id,
            'module_id' => $module_id
        ]);

        $keys = array();
        $vals = array();
        foreach ($data as $key => $value) {
            $keys[] = $key;
            $vals[] = $this->db->escape($value);
        }

        $sql = "INSERT INTO " . DB_PREFIX . "minimenu ( `" . implode("`,`", $keys) . "`) VALUES ('" . implode("','", $vals) . "')";
        $this->db->query($sql);
        $item_id = $this->db->getLastId();

        if (isset($category["language"])) {
            $sql = " DELETE FROM " . DB_PREFIX . "minimenu_description WHERE item_id=" . $item_id;
            $this->db->query($sql);

            foreach ($category["language"] as $key => $categorydes) {
                $sql = "INSERT INTO " . DB_PREFIX . "minimenu_description(`language_id`, `item_id`,`title`)
							VALUES(" . $key . ",'" . $item_id . "','" . $this->db->escape($categorydes['name']) . "') ";
                $this->db->query($sql);
            }
        }
    }
}