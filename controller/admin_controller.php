<?php
/**
*
* @package Reset Post Count Extension
* @copyright (c) 2015 david63
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace david63\resetpostcount\controller;

use phpbb\config\config;
use phpbb\request\request;
use phpbb\db\driver\driver_interface;
use phpbb\template\template;
use phpbb\user;
use phpbb\language\language;
use phpbb\log\log;
use david63\resetpostcount\core\functions;

/**
* Admin controller
*/
class admin_controller implements admin_interface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string phpBB root path */
	protected $root_path;

	/** @var string PHP extension */
	protected $phpEx;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var \david63\resetpostcount\core\functions */
	protected $functions;

	/** @var string phpBB tables */
	protected $tables;

	/** @var string Custom form action */
	protected $u_action;

	/**
	* Constructor for admin controller
	*
	* @param \phpbb\config\config					$config				Config object
	* @param \phpbb\request\request					$request			Request object
	* @param \phpbb\db\driver\driver_interface		$db					The db connection
	* @param \phpbb\template\template				$template			Template object
	* @param \phpbb\user							$user				User object
	* @param string									$phpbb_root_path    phpBB root path
	* @param string									$php_ext            phpBB extension
	* @param \phpbb\language\language				$language			Language object
	* @param \phpbb\log\log							$log				Log object
	* @param \david63\resetpostcount\core\functions	$functions			Functions for the extension
	* @param array									$tables				phpBB db tables
	*
	* @return \david63\resetpostcount\controller\admin_controller
	* @access public
	*/
	public function __construct(config $config, request $request, driver_interface $db, template $template, user $user, $root_path, $php_ext, language $language, log $log, functions $functions, $tables)
	{
		$this->config		= $config;
		$this->request		= $request;
		$this->db			= $db;
		$this->template		= $template;
		$this->user			= $user;
		$this->root_path	= $root_path;
		$this->php_ext		= $php_ext;
		$this->language		= $language;
		$this->log			= $log;
		$this->functions	= $functions;
		$this->tables		= $tables;
	}

	/**
	* Process the post reset
	*
	* @return null
	* @access public
	*/
	public function reset_post_count()
	{
		// Add the language files
		$this->language->add_lang(array('acp_resetpostcount', 'acp_common'), $this->functions->get_ext_namespace());

		// Create a form key for preventing CSRF attacks
		$form_key = 'reset_post_count';
		add_form_key($form_key);

		$option			= $this->request->variable('option', '');
		$overide		= $this->request->variable('overide', '');
		$post_count		= $this->request->variable('post_count', 0);
		$reset_username	= $this->request->variable('reset_username', '', true);
		$reset_value	= $this->request->variable('reset_value', '');
		$reset_zero		= $this->request->variable('reset_zero', '');
		$user_id		= $this->request->variable('user_id', 0);

		$back		= false;
		$confirm	= false;
		$errors		= $hidden_fields = [];

		// Submit
		if ($this->request->is_set_post('submit'))
		{
			// Is the submitted form is valid?
			if (!check_form_key($form_key))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			switch ($option)
			{
				case 'update':
					if (!$reset_value && !$reset_zero)
					{
						$errors[] = $this->language->lang('ERROR_NO_DATA_SPECIFIED');
					}

					if (($reset_value > $post_count) && !$overide)
					{
						$errors[] = $this->language->lang('ERROR_RESET_GREATER');
					}

					if (!count($errors))
					{
						$new_post_count = ($reset_zero) ? 0 : $reset_value;

					// Update db
					$this->db->sql_query(
						'UPDATE ' . $this->tables['users'] . '
							SET user_posts = ' . (int) $new_post_count . '
							WHERE user_id = ' . (int) $user_id
						);

					// Log the action
					$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_USER_POST_COUNT_RESET',  time(), array($reset_username, $post_count, $new_post_count));
					$this->log->add('user', $this->user->data['user_id'], $this->user->ip, 'LOG_USER_POST_COUNT_RESET', time(), array('reportee_id' => $this->user->data['username'], $reset_username, $post_count, $new_post_count));
					trigger_error($this->language->lang('USER_POST_COUNT_RESET', $reset_username, $post_count, $new_post_count) . adm_back_link($this->u_action));
					}
					else
					{
						$confirm = true;

						$hidden_fields = array(
							'option'			=> 'update',
							'overide'			=> $overide,
							'post_count'		=> $post_count,
							'reset_username'	=> $reset_username,
							'user_id'			=> $user_id,
						);
					}
				break;

				default:
					if (!$reset_username)
					{
						$errors[] = $this->language->lang('ERROR_NO_USER_SPECIFIED');
					}
					else
					{
						$sql = 'SELECT user_id, user_posts
							FROM ' . $this->tables['users'] . "
							WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($reset_username)) . "'";
						$result = $this->db->sql_query($sql);

						$row = $this->db->sql_fetchrow($result);
						$this->db->sql_freeresult($result);

						$user_id	= $row['user_id'];
						$post_count	= $row['user_posts'];

						if (!$user_id)
						{
							$errors[] = $this->language->lang('ERROR_INVALID_USER_SPECIFIED');
						}

						if ($post_count == 0 && !$overide)
						{
							$errors[] = $this->language->lang('ERROR_NO_POST_COUNT');
						}
					}

					if (!count($errors))
					{
						$confirm = true;

						$hidden_fields = array(
							'option'			=> 'update',
							'overide'			=> $overide,
							'post_count'		=> $post_count,
							'reset_username'	=> $reset_username,
							'user_id'			=> $user_id,
						);
					}
				break;
			}
		}

		// Template vars for header panel
		$version_data	= $this->functions->version_check();

		$this->template->assign_vars(array(
			'DOWNLOAD'			=> (array_key_exists('download', $version_data)) ? '<a class="download" href =' . $version_data['download'] . '>' . $this->language->lang('NEW_VERSION_LINK') . '</a>' : '',

			'ERROR_TITLE'		=> $this->language->lang('WARNING'),
			'ERROR_DESCRIPTION'	=> implode('<br>', $errors),

			'HEAD_TITLE'		=> $this->language->lang('RESET_POST_COUNT'),
			'HEAD_DESCRIPTION'	=> $this->language->lang('RESET_POST_COUNT_EXPLAIN'),

			'NAMESPACE'			=> $this->functions->get_ext_namespace('twig'),

			'S_BACK'			=> $back,
			'S_VERSION_CHECK'	=> (array_key_exists('current', $version_data)) ? $version_data['current'] : false,

			'VERSION_NUMBER'	=> $this->functions->get_meta('version'),
		));

		$this->template->assign_vars(array(
			'L_RESET_USER_DETAILS'		=> $this->language->lang('RESET_USER_DETAILS', $reset_username, $post_count),

			'S_CONFIRM'					=> $confirm,
			'S_HIDDEN_FIELDS'			=> build_hidden_fields($hidden_fields),

			'U_ACTION'					=> $this->u_action,
			'U_FIND_USERNAME'			=> append_sid("{$this->root_path}memberlist.$this->php_ext", 'mode=searchuser&amp;form=reset_post_count&amp;field=reset_username&amp;select_single=true'),
		));
	}

	/**
	* Set page url
	*
	* @param string $u_action Custom form action
	* @return null
	* @access public
	*/
	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}
}
