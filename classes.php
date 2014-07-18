<?php

/* 
 * PJ Plugin PHP file
 */

class PJ_AG_Controller {
  public $model, $view;
  private $plugin_name, $plugin_slug, 
          $current_setting_group, $current_setting_page;
  
  /*
   * Construct controller class
   * set model and view class
   * declare actions to be used
   */
  public function __construct($plugin_name=null) {
    //Set model and view
    $this->model = new PJ_AG_Model();
    $this->view = new PJ_AG_View();
    
    //check Plugin_name
    if ($plugin_name) {
      $this->plugin_name = $plugin_name;
      $this->plugin_slug = str_replace(' ', '-', $plugin_name);
   
    
      //Declare actions
      add_action('admin_init', array($this, 'admin_init_groups'));
      add_action('admin_menu', array($this, 'admin_menu'));
    }
  }
  
  //Called by activation hook, registers plugin data
  public function register_plugin() {
    $this->model->initialise_meta_setting_groups();
    $this->model->register_plugin();
  }
  
  //create the admin_menu and sub_menus (based on groups in model's model array) for this plugin
  public function admin_menu() {
    add_menu_page($this->plugin_name, $this->plugin_name, 'manage_options', $this->plugin_slug);
    $sub_menus = $this->model->get_model();
    $first_sub = true;
    foreach ($sub_menus as $sub_menu) {
      $sub_slug = $this->plugin_slug;
      if (!$first_sub) {
        $sub_slug .= '_' . $sub_menu['id'];
      } else {
        $first_sub = false;
      }
      add_submenu_page($this->plugin_slug,
              $sub_menu['title'],
              $sub_menu['title'],
              'manage_options',
              $sub_slug,
              array($this, 'create_sub_menu'));
    }
  }
  
  //register settings to be used
  public function admin_init_groups() {
    $plugin_settings_groups = $this->model->get_model();
    $first_sub = true;
    foreach ($plugin_settings_groups as $current_group) {
      register_setting($current_group['id'], $current_group['id']);
      $page = $this->plugin_slug;
      if (!$first_sub) {
        $page .= '_' . $current_group['id'];
      } else {
        $first_sub = false;
      }
      $this->admin_init_sections($current_group['sections'], $page);
    }
  }
  
  //Initiate sections for group being processed in admin_init_groups
  private function admin_init_sections($sections, $page) {
    foreach ($sections as $section) {
        add_settings_section(
                $section['id'],
                $section['title'],
                array($this, 'setting_section_callback'),
                $page);
        $this->admin_init_settings($section['settings'], $section['id'], $page);
    }    
  }
  
  //Initiate settings for section being processed in admin_init_sections
  private function admin_init_settings($settings, $section_id, $page) {
    foreach($settings as $setting) {
      add_settings_field(
              $setting['id'],
              $setting['title'],
              array($this, 'settings_field'),
              $page,
              $section_id,
              array('setting_id'=>$setting['id'], 'setting_type'=>$setting['type']));
    }
  }
  
  /*
   * Create a text field for setting
   * $setting_id setting ID passed from do_settings_fields, set in add_settings_field
   */
  public function settings_field($setting) {
    $callback = array($this->view, 'render_setting_'.$setting['setting_type']);
    $args = array(
        $setting['setting_id'],
        $this->current_setting_group['id'],
        $this->model->get_options($this->current_setting_group['id'],$setting['setting_id'])
        );
    switch ($setting['setting_type']) {
      case 'text' :
        break;
      case 'dropdown' :
        $args[] = $this->model->get_setting_items($this->current_setting_group['id'], $setting['setting_id']);
    }
    call_user_func_array($callback, $args);
  }
  
  //to display a sub menu from the admin menu
  public function create_sub_menu() {
    if ($this->find_current_group()) {
      $this->view->render_sub_menu_header(get_admin_page_title(), $this->current_setting_group['description']);
      settings_fields($this->current_setting_group['id']);
      do_settings_sections($this->current_setting_page);
      $this->view->render_sub_menu_footer();
    }
  }
  
  /*
   * Find the setting group being used in the current admin sub_menu
   * Return true or false
   */
  public function find_current_group() {
    $title = get_admin_page_title();
    if (!$this->current_setting_group) {
      $groups = $this->model->get_model();
      $first = true;
      foreach ($groups as $group) {
        if ($group['title'] == $title) {
          $this->current_setting_group = $group;
          if ($first) { 
            $this->current_setting_page = $this->plugin_slug;
          } else {
            $this->current_setting_page = $this->plugin_slug . '_' . $group['id'];
          }
          return true;
        }
        $first = false;
      }
    } else {
      return true;
    }
    return false;
  }
  
  //display setting section description
  public function setting_section_callback($arg) {
    if ($this->find_current_group()) {
      $description = $this->current_setting_group['sections'][$arg['id']]['description'];
      $this->view->render_setting_section($description);
    }
  }
} //End Controller

class PJ_AG_View {
  //create wrapper div and form for sub_menu with description of sub_menu if set
  public function render_sub_menu_header($title, $description) {
    echo '<div class="wrap"><h1>'. $title .'</h1>';
    if ($description) {
      echo '<p>'. $description .'</p>';
    }
    echo '<form action="options.php" method="post">';
  }
  
  //close off opened div and form from header
  public function render_sub_menu_footer() {
    ?>
    <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
    </form></div>
    <?php
  }
  
  //create the description for setting section
  public function render_setting_section($description) {
    echo '<p>' . $description . '</p>';
  }
  
  /*
   * Create input for a setting of type 'text'
   * @param setting_id the id of the given setting
   * @param setting_group_id the id of the group the setting is for
   * @param current_value the current value of the setting
   */
  public function render_setting_text($setting_id, $setting_group_id, $current_value) {
    echo "<input id='{$setting_id}' name='{$setting_group_id}[{$setting_id}]' size='40' type='text' value='{$current_value}' />";
  }
  
  /*
   * Create select for a dropdown input (setting type 'dropdown')
   * @param setting_id the id of the given setting
   * @param setting_group_id the id of the group the setting is for
   * @param current_value the current value of the setting
   * @param items array of items to be put into dropdown list
   */
  public function render_setting_dropdown($setting_id, $setting_group_id, $current_value, $items) {
    echo "<select id='{$setting_id}' name='{$setting_group_id}[{$setting_id}]'>";
    foreach ($items as $item) {
      echo "<option value='{$item}' ";
      if ($item==$current_value) {
        echo "selected='selected'";
      }
      echo ">{$item}</option>";
    }
    echo "</select>";
  }
} //End View

class PJ_AG_Model {
  private $meta_settings_groups, $options;
  
  //initialise the meta settings groups for plugin
  public function initialise_meta_setting_groups() {
    $settings_groups = array(
        'setting_group1'=>array(
            'title'=>'Group 1',
            'id'=>'setting_group1',
            'description'=>'First group of settings',
            'sections'=>array(
                'section1_1'=>array(
                    'title'=>'Section1 of Group 1',
                    'id'=>'section1_1',
                    'description'=>'This is a description for the first section of Group 1',
                    'settings'=>array(
                        'setting1'=>array( 
                            'title'=>'Setting 1 of Section 1 in Group 1',
                            'id'=>'setting1',
                            'description'=>'The first setting used in the first group',
                            'type'=>'text',
                            'default'=>'MySetting1'
                        ), //End of item
                        'setting2'=>array(
                            'title'=>'Setting 2 of Group 1',
                            'id'=>'setting2',
                            'description'=>'The second setting used in the first group',
                            'type'=>'text',
                            'default'=>'MySetting2'
                        ), //End of item
                        'setting3'=>array(
                            'title'=>'Setting 2 of Group 1',
                            'id'=>'setting2',
                            'description'=>'The second setting used in the first group',
                            'type'=>'text',
                            'default'=>'NewDefaultSetting2'
                        ), //End of item
                    ) //End of items
                ), //End of section
                'section2_1'=>array(
                    'title'=>'Section 2 of Group 1',
                    'id'=>'section2_1',
                    'description'=>'This is a description for the first section of Group 1',
                    'settings'=>array(
                        'setting1'=>array( 
                            'title'=>'Setting 1 of Section 2',
                            'id'=>'s2_setting1',
                            'description'=>'The first setting used in the first group',
                            'type'=>'text',
                            'default'=>'MySetting1'
                        ), //End of item
                        'setting2'=>array(
                            'title'=>'Setting 2 of Section 2',
                            'id'=>'s2_setting2',
                            'description'=>'The second setting used in the first group',
                            'type'=>'text',
                            'default'=>'MySetting2'
                        ), //End of item
                        'setting3_dropdown'=>array(
                            'title'=>'Dropdown Setting',
                            'id'=>'setting3_dropdown',
                            'description'=>'Dropdown setting for check',
                            'type'=>'dropdown',
                            'default'=>'drop1',
                            'items'=>array('drop1','drop2','drop3')
                        ), //End of item
                    ) //End of items
                ) //End of section
            ) //End of sections
        ),//End of group
        'setting_group2'=>array(
            'title'=>'Group 2',
            'id'=>'setting_group2',
            'description'=>'Second group of settings',
            'sections'=>array(
                'section1_2'=>array(
                    'title'=>'Section1 of Group 2',
                    'id'=>'section1_2',
                    'description'=>'This is a description for the first section of Group 2',
                    'settings'=>array(
                        'setting1'=>array( 
                            'title'=>'Setting 1 of Group 2',
                            'id'=>'setting1',
                            'description'=>'The first setting used in the first group',
                            'type'=>'text',
                            'default'=>'MySetting1'
                        ), //End of setting
                        'setting2'=>array(
                            'title'=>'Setting 2 of Group 2',
                            'id'=>'setting2',
                            'description'=>'The second setting used in the first group',
                            'type'=>'text',
                            'default'=>'MySetting2'
                        ), //End of setting
                        'setting3'=>array(
                            'title'=>'Setting 3 of Group 2',
                            'id'=>'setting3',
                            'description'=>'The second setting used in the first group',
                            'type'=>'text',
                            'default'=>'setting3'
                        ), //End of setting
                        'setting4_dropdown'=>array(
                            'title'=>'Dropdown Setting',
                            'id'=>'setting4_dropdown',
                            'description'=>'Dropdown setting for check',
                            'type'=>'dropdown',
                            'default'=>'drop2 g2s1',
                            'items'=>array('drop1 g2s1','drop2 g2s1','drop3 g2s1')
                        ), //End of item
                    ) //End of settings
                ) // End of section
            )//End of sections
        ),//End of group
        'pj_ag_general'=>array(
            'title'=>'General Settings for PJ Admin General',
            'id'=>'pj_ag_general',
            'description'=>'General settings for PJ Admin General.',
            'sections'=>array(
                'text'=>array(
                    'title'=>'Text settings',
                    'id'=>'text',
                    'description'=>'Text section for General settings',
                    'settings'=>array(
                        'text1'=>array( 
                            'title'=>'Title',
                            'id'=>'text1',
                            'description'=>'The title to be used in PJ Admin General',
                            'type'=>'text',
                            'default'=>'PJ Admin General'
                        ), //End of setting
                        'text2'=>array(
                            'title'=>'Sub-title',
                            'id'=>'text2',
                            'description'=>'The sub-title to be used in PJ Admin General',
                            'type'=>'text',
                            'default'=>'A plugin for showcasing generalised plugin options'
                        ), //End of setting
                    ) //End of settings
                ), // End of section
                'dropdown'=>array(
                    'title'=>'Dropdown Settings',
                    'id'=>'dropdown',
                    'description'=>'Dropdown settings section for General Settings',
                    'settings'=>array(
                        'dropdown1'=>array(
                            'title'=>'Fruits',
                            'id'=>'dropdown1',
                            'description'=>'Select a fruit from the dropdown menu',
                            'type'=>'dropdown',
                            'default'=>'Apple',
                            'items'=>array('Apple','Banana','Pear','Grapefruit')
                        ),//End of setting (dropdown1)
                        'dropdown2'=>array(
                            'title'=>'Vegetables',
                            'id'=>'dropdown2',
                            'description'=>'Select a vegetable from the dropdown menu',
                            'type'=>'dropdown',
                            'default'=>'Carrot',
                            'items'=>array('Brocolli','Pea','Carrot','Squash')
                        ),//End of setting (dropdown1)
                    )//End of settings
                )//End of section
            )//End of sections
        )//End of group
    );//End of model
    $this->meta_settings_groups = $settings_groups;
  }
  
  //register all options, called by controller when plugin is activated
  public function register_plugin() {
    $setting_groups = $this->meta_settings_groups;
    foreach ($setting_groups as $setting_group) {
      $setting_name = $setting_group['id'];
      $settings = $this->get_default_settings($setting_group['id']);
      update_option($setting_name, $settings);
    }
  }
  
  //return default settings for the group of setting_group_id passed as argument
  public function get_default_settings($setting_group_id) {
    $return_settings = array();
    foreach($this->get_all_settings_from_group($setting_group_id) as $setting) {
      $return_settings[$setting['id']] = $setting['default'];
    }
    if ($this->check_option($setting_group_id)) {
      return $this->update_default_settings($setting_group_id, $return_settings);
    }
    return $return_settings;
  }
  
  //update options that are already set
  private function update_default_settings($setting_group_id, $settings) {
    $options = $this->options[$setting_group_id];
    foreach ($settings as $key => $value) {
      if (isset($options[$key])) {
        if ($options[$key]!=$value) {
          $settings[$key] = $options[$key];
        }
      }
    }
    return $settings;
  }
  
  /*
   * Get options, this function is to return the options set in the DB, on first use
   * it will query using get_option function, thereafter the options for the setting_group
   * have been stored into the class variable options for getting without querying the DB.
   * @param $setting_group_id required string of specific setting group
   * @param $setting_id optional string for required setting
   * return all options for given group or just specified option
   */
  public function get_options($setting_group_id, $setting_id=NULL) {
    if(!$this->check_option($setting_group_id)) { //if initialise returns false (no options in db)
      return false;
    }
    $current_options_group = $this->options[$setting_group_id];
    if ($setting_id && isset($current_options_group[$setting_id])) {
      return $current_options_group[$setting_id];
    } else if ($setting_id) { //if there is a setting_id passed and no actual setting
      return false;
    } else { //setting_id is null, return entire group
      return $current_options_group;
    }
  }
  
  //get list of accepted items from setting
  public function get_setting_items($setting_group_id, $setting_id) {
    foreach ($this->get_all_settings_from_group($setting_group_id) as $setting) {
      if ($setting['id']==$setting_id) {
        return $setting['items'];
      }
    }
  }
  
  //return all settings from group given (disregard sections
  private function get_all_settings_from_group($setting_group_id) {
    if (!$this->meta_settings_groups) {
      $this->initialise_meta_setting_groups();
    }
    $return_array = array();
    foreach ($this->meta_settings_groups[$setting_group_id]['sections'] as $section) {
      foreach ($section['settings'] as $setting) {
        $return_array[] = $setting;
      }
    }
    return $return_array;
  }
  
  /*
   * Current: return full model
   * TODO - allow for returning smaller versions of model
   */
  public function get_model() {
    if (!$this->meta_settings_groups) {
      $this->initialise_meta_setting_groups();
    }
    return $this->meta_settings_groups;    
  }
  
  //Initialise options for given $setting_group, return true if options set
  private function initialise_options() {
    if (!$this->meta_settings_groups) { 
      $this->initialise_meta_setting_groups();
    }
    foreach ($this->meta_settings_groups as $setting_group) {
      $this->options[$setting_group['id']] = get_option($setting_group['id']);
    }
  }
  
  //check that option is more than blank
  private function check_option($setting_group_id) {
    if (!isset($this->options[$setting_group_id])) {
      $this->initialise_options();
    }
    if ($this->options[$setting_group_id]==FALSE) {
      return false;
    } else {
      return true;
    }
  }
  
} //End Model