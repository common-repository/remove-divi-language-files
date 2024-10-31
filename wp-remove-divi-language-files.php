<?php
/*
Plugin Name: Remove Divi Language Files
Plugin URI: https://wpress-cursus.nl/plugins
Description: Remove unused language files from the Divi theme
Version: 1.1.1
Author: WPress Cursus
Author URI: https://wpress-cursus.nl
License: GPL v3
Text Domain: remove-divi-language-files
Domain Path: /languages
*/

/**
 * Remove Divi Language Files
 * Copyright (C) 2016, WPress Cursus - plugin@wpress-cursus.nl
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class WP_Remove_Divi_Language_Files {
	const TITLE = 'Remove Divi Language Files';
	const TEXT_DOMAIN = 'remove-divi-language-files';

	const DIVI_LOCATION = 'Divi'; 
	const MENU = 'Remove Divi Language Files';

	private $actions = array(
		'admin_menu' => 'admin_menu',
	);

	private $language_folders = array(
		'lang',
		'core'.DIRECTORY_SEPARATOR.'languages',
		'includes/builder'.DIRECTORY_SEPARATOR.'languages',
	);

	private $deleted_files = 0;
	private $file_size = 0;

	private $exclude_languages = array(
		'en_US',
	);	

	public function __construct() {
		$this->exclude_languages[] = get_locale();
		foreach($this->actions as $tag => $callback) {
			add_action($tag, array($this, $callback));
		}

		$dry_run = true;
		$deleted_files = $this->delete_language_files($dry_run);
		$this->deleted_files = count($deleted_files);
	}

	public function admin_menu() {
		add_options_page(
			__(WP_Remove_Divi_Language_Files::MENU, WP_Remove_Divi_Language_Files::TEXT_DOMAIN),
			__(WP_Remove_Divi_Language_Files::MENU, WP_Remove_Divi_Language_Files::TEXT_DOMAIN), 
			'manage_options',
			WP_Remove_Divi_Language_Files::TEXT_DOMAIN, 
			array($this, 'create_remove_files_page')
		);
	}

	public function create_remove_files_page() {
		$message = '';
		if ($_POST['submit']) {
			$deleted_language_files = $this->delete_language_files();
			$message = $this->get_message($deleted_language_files);
		}

		echo $this->get_page_html($message);
	}

	private function get_page_html($message) {
		$page_html = $this->build_page_divi_not_installed();
		if ($this->is_divi_exists()) {
			$language_files = $this->get_language_files();
			$page_html = $this->build_page_html($language_files, $message);
		}
		return $page_html;
	}

	private function get_message($deleted_language_files) {
		$deleted_files_list = '';
		foreach($deleted_language_files as $file) {					
			$deleted_files_list .= '<li>' . $file . '</li>';
		}

		if ($deleted_files_list!=='') {
			$message = sprintf('<strong>%s</strong>',
				__('The following files were deleted:', WP_Remove_Divi_Language_Files::TEXT_DOMAIN));
			$message .= sprintf('<ul>%s</ul>', $deleted_files_list);
		}

		if ($deleted_files_list==='') {
			$message = __('No files deleted', WP_Remove_Divi_Language_Files::TEXT_DOMAIN);
		}

		return $message;
	}

	private function delete_language_files($dry_run=false) {
		$deleted_files = array();
		foreach($this->get_language_files() as $folder => $files) {
			foreach($files as $file) {
				if (!$this->is_file_excluded($file)) {
					$path = get_theme_root() . DIRECTORY_SEPARATOR . WP_Remove_Divi_Language_Files::DIVI_LOCATION . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $file;
					
					if ($dry_run) {
						$this->file_size += filesize($path);
					}

					if (!$dry_run) {
						unlink($path);
					}
					$deleted_files[] = $folder . DIRECTORY_SEPARATOR . $file;
				}
			}
		}
		if(!$dry_run) {
			$this->deleted_files = 0;
		}
		return $deleted_files;
	}

	private function is_file_excluded($file) {
		foreach($this->exclude_languages as $excluded_language) {
			if ($this->starts_with($file, $excluded_language)) {
				return true;
			}
		}
		return false;
	}

	private function build_page_divi_not_installed() {
		$html = sprintf('
			<div class="wrap">
				<h1>%s</h1>
				%s
			</div>',
			__(WP_Remove_Divi_Language_Files::TITLE, WP_Remove_Divi_Language_Files::TEXT_DOMAIN),
			__('Oops, this plugin requires the Divi theme to be installed')
		);
		return $html;
	}

	private function build_page_html($language_files, $message='') {
		$html = sprintf('
			<div class="wrap">
				<h1>%s</h1>
				%s
				<form action="" method="post">
					<table class="form-table">
						%s
						%s
					</table>
					%s
				</form>
			</div>',
			__(WP_Remove_Divi_Language_Files::TITLE, WP_Remove_Divi_Language_Files::TEXT_DOMAIN),
			$this->get_message_html($message),
			$this->get_not_deleting_html(),
			$this->get_to_delete_html(),
			$this->get_submit_html()
		);

		return $html;
	}

	private function get_submit_html() {
		$html = '';
		if ($this->deleted_files !== 0) {
			$html = get_submit_button(
				__('Remove Divi language files', WP_Remove_Divi_Language_Files::TEXT_DOMAIN)
			);
		}
		return $html;
	}

	private function get_to_delete_html() {
		$html = __('Yay, nothing to remove!', WP_Remove_Divi_Language_Files::TEXT_DOMAIN);
		if ($this->deleted_files !== 0) {
			$html = sprintf(
			__('%d files (%s) can be deleted.', WP_Remove_Divi_Language_Files::TEXT_DOMAIN),
				$this->deleted_files,
				$this->format_bytes($this->file_size)
			);
		}
		return $this->wrap_in_html_table_row_and_cell($html);
	}

	private function get_not_deleting_html() {
		$html = '';
		if ($this->deleted_files !== 0) {
			$html = sprintf(
				__('Not deleting the following languages: %s', WP_Remove_Divi_Language_Files::TEXT_DOMAIN),
					'<strong>' . 
					implode('</strong>, <strong>', $this->exclude_languages) . 
					'</strong>'
			);
			$html = $this->wrap_in_html_table_row_and_cell($html);
		}
		return $html;
	}

	private function get_message_html($message) {
		return ($message==='' ? '' : sprintf(
				'<div class="updated notice"><p>%s</p></div>',
				$message
			)
		);
	}

	private function is_divi_exists() {
		return file_exists(get_theme_root() . DIRECTORY_SEPARATOR . WP_Remove_Divi_Language_Files::DIVI_LOCATION);
	}

	private function get_language_files() {
		$files = array();
		foreach($this->language_folders as $folder) {
			$files[$folder] = array_diff(
				scandir(get_theme_root() . DIRECTORY_SEPARATOR .WP_Remove_Divi_Language_Files::DIVI_LOCATION . DIRECTORY_SEPARATOR . $folder),
				array('.', '..')
			);
		}
		return $files;
	}

	function wrap_in_html_table_row_and_cell($html) {
		return '<tr><td>' . $html . '</td></tr>';
	}

	function format_bytes($bytes, $precision = 2) { 
		$units = array('B', 'KB', 'MB', 'GB', 'TB'); 

		$bytes = max($bytes, 0); 
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
		$pow = min($pow, count($units) - 1); 
		$bytes /= pow(1024, $pow);

		return round($bytes, $precision) . ' ' . $units[$pow]; 
	} 	

	function starts_with($haystack, $needle) {
		$length = strlen($needle);
		return (substr($haystack, 0, $length) === $needle);
	}
}

new WP_Remove_Divi_Language_Files();
