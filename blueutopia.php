<?php
/*
Plugin Name: Blue Utopia Donate Now
Plugin URI: http://my.blueutopia.com/admin/apps/wordpress/donate/
Description: Adds a ‘Donate Now’ button to your WP site that connects your visitors directly to your custom-branded Blue Utopia Donation page.
Version: 1.0.1
Author: Blue Utopia
Author URI: http://blueutopia.com
License: GPLv2 or later
*/
/*  Copyright 2013 Blue Utopia  (email : info@blueutopia.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

	// Make sure we don't expose any info if called directly
	if ( !function_exists('add_action')) {
		echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
		exit;
	}
	require_once('assets/api.php');

	$blueutopia = new BlueUtopiaDonate();
	if (is_admin()){		
		add_action('admin_init', array($blueutopia,'adminSettings'));
		add_action('admin_head',  array($blueutopia,'adminHeader'));
		add_action('admin_footer', array($blueutopia,'adminFooter'));
		add_action('admin_menu', array($blueutopia,'AdminAddMenu'));
		add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($blueutopia,'adminActionLinks'));
		add_filter('plugin_row_meta', array($blueutopia,'adminMetaLinks'), 10, 2 );
	}
	
	add_action('init', array($blueutopia,'init'));
	add_action('widgets_init', create_function('', 'return register_widget("BlueUtopiaDonateWidget");'));

	class BlueUtopiaDonateWidget extends WP_Widget {
		public $name;
		public $slug;
		public $base;
		public $description;
		public $base_plugin;
		public $plugin;
				
  	function __construct() {
			$this->name = 'Blue Utopia Donate Now';
			$this->description = 'Allow people to donate to your campain/organization.';
			$this->slug = 'blueutopiadonate';
			$this->base = strtolower(get_class($this));
			$this->plugin = new BlueUtopiaDonate();
			$this->base_plugin = strtolower(get_class($this->plugin));

			
    	$widget_ops = array('classname'=>$this->base, 'description'=>$this->description);
    	$this->WP_Widget($this->base, $this->name, $widget_ops);
  	}
		
		private function getStyles($folder=NULL){
			if(isset($folder) and trim($folder)!=''){
				if(is_dir($folder) and is_readable($folder)){
					if ($handle = opendir($folder)) {
						while (false !== ($entry = readdir($handle))) {
							if ($entry != "." and $entry != ".." and $entry != ".svn" and is_dir($folder.'/'.$entry)) {
								$folders[] = $entry;
							}
						}
						closedir($handle);
					}
					if(isset($folders) and is_array($folders)){
						foreach($folders as $k=>$v){
							if(file_exists($folder.'/'.$v.'/style.css') and is_readable($folder.'/'.$v.'/style.css')){
								unset($name,$value);
								$name = str_replace("_", " ", $v);
								$name = ucfirst(strtolower($name));
								$results[$name] = $v;	
								unset($name,$value);
							}
							unset($k,$v);
						}
					}
					
					if(isset($results) and is_array($results)){
						return $results;
					} else {
						return false;
					}
				} else {
					return false;
				}
			} else {
				return false;
			}
		}

		public function form($instance) {
			$targets = array('_blank','_self','_parent','_top');
    	$instance = wp_parse_args((array) $instance, array('title'=>'','button_value'=>'','style'=>'default','target'=>'_blank'));
    	$title = $instance['title'];
			$button_value = $instance['button_value'];
			$style = $instance['style'];
			$target = $instance['target'];
			
			$error = true;			
			if(trim(get_option($this->base_plugin.'_api_key'))!=''){
				try {
					$account_info = $this->plugin->get('account.json?access_token='.trim(get_option($this->base_plugin.'_api_key')));
					if($account_info){
						$account_info = json_decode($account_info);		
						$error = false;				
					}
				} catch (Exception $e) {}		
			}
			
			if($error){
				if(trim(get_option($this->base_plugin.'_api_key'))!=''){
					print '<p>Error api key is invalid. <a href="/wp-admin/options-general.php?page='.$this->slug.'" target="_blank">Click here</a> to fix it.</p>';
				} else {
					print '<p>Error api key has not been set. <a href="/wp-admin/options-general.php?page='.$this->slug.'" target="_blank">Click here</a> to set it.</a></p>';
				}
			} else {
				if(isset($account_info->info->secure_url) and trim($account_info->info->secure_url)!=''){					
					print '<p>';
					print '<label for="'.$this->get_field_id('title').'">Title:';
					print '<input class="widefat" id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" 
		type="text" value="'.attribute_escape($title).'" placeholder="Donate Now" />';
					print '</label>';
					print '</p>';

					print '<p>';
					print '<label for="'.$this->get_field_id('button_value').'">Button:';
					print '<input class="widefat" id="'.$this->get_field_id('button_value').'" name="'.$this->get_field_name('button_value').'" 
		type="text" value="'.attribute_escape($button_value).'" placeholder="Donate Now" maxlength="20" />';
					print '</label>';
					print '</p>';
					
					$styes = $this->getStyles(dirname(__FILE__).'/assets/styles/');
					if($styes){
						print '<p>';
						print '<label for="'.$this->get_field_id('style').'">Style:';
						print '<select class="widefat" id="'.$this->get_field_id('style').'" name="'.$this->get_field_name('style').'">';
						foreach($styes as $k=>$v){
							print '<option value="'.$v.'" label="'.$k.'"';
							if($v==$style){
								print ' selected';
							}
							print '>'.$k.'</option>';							
							unset($k,$v);
						}
						print '</select>';
						print '</label>';
						print '</p>';
					}

					if(isset($targets) and is_array($targets)){
						print '<p>';
						print '<label for="'.$this->get_field_id('target').'">Target:';
						print '<select class="widefat" id="'.$this->get_field_id('target').'" name="'.$this->get_field_name('target').'">';
						foreach($targets as $k=>$v){
							print '<option value="'.$v.'" label="'.$v.'"';
							if($v==$target){
								print ' selected';
							}
							print '>'.$v.'</option>';							
							unset($k,$v);
						}
						print '</select>';
						print '</label>';
						print '</p>';
					}

				} else {
					print '<p>Error donate url has not been set. Please contact <a href="http://my.blueutopia.com/admin/pages/193/" target="_blank">Blue Utopia Support</a>.</p>';
				}
			}

		}

  	public function update($new_instance, $old_instance) {
    	$instance = $old_instance;
    	$instance['title'] = $new_instance['title'];
			$instance['button_value'] = $new_instance['button_value'];
			$instance['style'] = $new_instance['style'];
			$instance['target'] = $new_instance['target'];
			return $instance;
  	}

  	public function widget($args, $instance) {
    	extract($args, EXTR_SKIP);
    	$title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
			$button_value = empty($instance['button_value']) ? ' ' : apply_filters('widget_title', $instance['button_value']);
			$target = $instance['target'];
			$style = $instance['style'];
				
			$error = true;			
			if(trim(get_option($this->base_plugin.'_api_key'))!=''){
				try {
					$account_info = $this->plugin->get('account.json?access_token='.trim(get_option($this->base_plugin.'_api_key')));
					if($account_info){
						$account_info = json_decode($account_info);		
						$error = false;				
					}
				} catch (Exception $e) {}		
			}			
			
			if(!$error){
				if(isset($account_info) and isset($account_info->info->secure_url) and trim($account_info->info->secure_url)!=''){		
					// WIDGET CODE GOES HERE
					
					print '<aside id="'.$args['widget_id'].'" class="widget widget_'.$this->slug.'">';
					if(isset($title) and trim($title)!=''){
						print '<h3 class="widget-title">'.trim($title).'</h3>';
					}
	
					if(!isset($button_value) or trim($button_value)==''){
						$button_value = 'Donate Now';
					}
					
					if(!isset($target) or trim($target)==''){
						$target = '_blank';
					}
					
					if(!isset($style) or trim($style)==''){
						$style = 'default';
					}
					
					//Load the css file 
						unset($cssfile,$css);
						$style = preg_replace("/[^A-Za-z0-9_]/", '', $style); //Don't allow periods to hack to folders you should not be looking at
						$cssfile = dirname(__FILE__).'/assets/styles/'.$style.'/style.css';
						if(!file_exists($cssfile) or !is_readable($cssfile) and $style!='default'){
							$cssfile = dirname(__FILE__).'/assets/styles/default/style.css';	
						}
						
						if(file_exists($cssfile) and is_readable($cssfile)){
							unset($fh);
							$fh = @fopen($cssfile, 'r');
							$css = @fread($fh, filesize($cssfile));
							fclose($fh);
							unset($fh);	
																				
							if(isset($css) and trim($css)!=''){
								// Remove comments
								$css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
								// End
								// Remove space after colons
								$css = str_replace(': ', ':', $css);
								// End
								// Remove whitespace
								$css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);
								// End
								print '<style type="text/css">'.$css.'</style>';
							}
						}
						unset($cssfile,$css);
					//End		

					print '<p>';
					print '<a href="'.trim($account_info->info->secure_url).'" target="'.$target.'" class="'.$this->slug.' '.$style.'">'.$button_value.'</a>';
					print '</p>';					
					
					print '</aside>';
					
					//End
				}
			}
  	}
	}
