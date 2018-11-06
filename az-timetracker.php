<?php
namespace AZTimeTracker;
/*
Plugin Name: AZ Time Tracker
Plugin URI: http://anvilzephyr.com/shop
Description: Create workspaces and tasks to track project time
Version: 2.0.2
Author: Amy Hill
Author URI: http://www.anvilzephyr.com

*/

if (!class_exists('AZTimeTracker')){
   class AZTimeTracker {

      private static $_instance = null;
      public $text_domain = 'az-time';
      public $slug = 'az-time';
      
      

      public static function get_instance() {
         if (self::$_instance == null) {
            self::$_instance = new AZTimeTracker();
         }

         return self::$_instance;
      }
      
      public function __construct(){
         define('AZTIME_DIR','az-timetracker');
         // run the install scripts upon plugin activation
         register_activation_hook(__FILE__, [__CLASS__, 'install']);
         // run the install scripts upon plugin activation
         register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);
         $this->load_classes();  
         add_action('admin_enqueue_scripts', [$this, 'enqueue_files']);

      }
      

      function load_classes(){
         $path    = __DIR__.'/classes';
         $files = scandir($path);
         foreach ($files as $filename){
            if (strpos($filename,'.php')){
               include(__DIR__ . "/classes/" . $filename);
            }
         }
      }
      
      function enqueue_files(){
         wp_enqueue_script('az-timetracker',  plugins_url().'/'.AZTIME_DIR.'/assets/timetracker.js', ['jquery'], time(), true);
         $screen = get_current_screen();
         wp_localize_script('az-timetracker', 'aztt_screen' , $screen->id);
         global $pagenow;
         global $post_type;
         if ('az-task' == $post_type && 'edit.php' == $pagenow )
         wp_enqueue_style( 'timetracker-css', plugins_url() . '/az-timetracker/assets/timetracker.css','', 1.0);
      }
      
      public static function install(){
         Base::install();
      }
      
      public static function uninstall(){
         Base::uninstall();
      }


   }
}
$aztt = AZTimeTracker::get_instance();
