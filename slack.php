<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Alissa Nedossekina <alisa@purdue.edu>
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

include_once \Component::path('com_publications') . DS . 'models' . DS . 'publication.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Project publications
 */
class plgProjectsSlack extends \Hubzero\Plugin\Plugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var  boolean
	 */
	protected $_autoloadLanguage = true;

	/**
	 * Component name
	 *
	 * @var  string
	 */
	protected $_option = 'com_projects';

	/**
	 * Store internal message
	 *
	 * @var	 array
	 */
	protected $_msg = null;

	/**
	 * Event call to determine if this plugin should return data
	 *
	 * @param   string  $alias
	 * @return  array   Plugin name and title
	 */
	public function &onProjectAreas($alias = null)
	{
		$area = array(
			'name'    => 'slack',
			'title'   => 'Slack',
			'submenu' => null,
			'show'    => false
		);

		return $area;
	}

	/**
	 * Event call to return data for a specific project
	 *
	 * @param   object  $model   Project model
	 * @param   string  $action  Plugin task
	 * @param   string  $areas   Plugins to return data
	 * @return  array   Return array of html
	 */
	public function onProject($model, $action = '', $areas = null)
	{
		$returnhtml = true;

		$arr = array(
			'html'     => '',
			'metadata' => '',
			'message'  => '',
			'error'    => ''
		);

		// Get this area details
		$this->_area = 'slack';

		// Check if our area is in the array of areas we want to return results for
		if (is_array($areas))
		{
			if (empty($this->_area) || !in_array($this->_area, $areas))
			{
				return;
			}
		}

		// Check authorization
		if ($model->exists() && !$model->access('member'))
		{
			return $arr;
		}

		// Model
		$this->model = $model;

		// Incoming
		$this->_task = Request::getString('action', '');

		// Actions
		switch ($this->_task)
		{
			case 'nonce':
				$this->generateNonce();
			case 'postMessage':
				$this->postMessage();
			default:
				$arr['html'] = '';
				break;
		}

		// Return data
		return $arr;
	}

	public function generateNonce()
	{
		$projectAlias = $this->model->get('alias');
		$slackRedirectUrl = rtrim(Request::root(), '/') . Route::url('index.php?option=' . $this->_option . '&alias=' . $projectAlias . '&active=slack');
		$token = \App::get('session')->getToken();
		$nonceData = array(
			'nonce' => $token
		);
		$msgData = array(
			'nonce' => $token,
			'scope' => 'project',
			'scope_id' => $this->model->get('id'),
			'redirect_uri' => 'http://localhost:8080/slack/saveToken',
			'final_redirect_uri' => $slackRedirectUrl 
		);
		$queue = 'hubzero.project.nonce_generate';
		$this->sendQueueMessage($queue, $msgData);
		header('Content-type: application/json');
		echo json_encode($nonceData);
		exit();
	}

	public function postMessage()
	{
		$msgData = array(
			'method' => 'postMessage',
			'scope' => 'project',
			'scope_id' => $this->model->get('id'),
			'message' => Request::getString('message', 'this message came from Slack Plugin')
		);
		$queue = 'hubzero.project.message';
		$this->sendQueueMessage($queue, $msgData);
		
		header('Content-type: application/json');
		echo json_encode($msgData);
		exit();
	}

	/**
	 * Event call to get side content
	 *
	 * @param   object  $model
	 *
	 * @return  mixed
	 */
	public function onProjectIntegrationList($model)
	{
		$projectAlias = $model->get('alias');
		$slackToken = $model->params->get('slack-token');
		$slackRedirectUrl = 'http://localhost:8080/slack/saveToken';
		$slackRedirectUrl = rawurlencode($slackRedirectUrl);
		$view = $this->view();
		$nonceUrl = Route::url('index.php?option=' . $this->_option . '&alias=' . $projectAlias . '&active=slack&action=nonce');
		$view->set('slackRedirectUrl', $slackRedirectUrl);
		$view->set('nonce', $nonceUrl);
		return $view->loadTemplate();
	}

	private function sendQueueMessage($queue, $content)
	{
		$connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
		$channel = $connection->channel();
		$channel->queue_declare($queue, false, false, false, false);
		$msgData = is_string($content) ? $content : json_encode($content);
		$msg = new AMQPMessage($msgData);
		$channel->basic_publish($msg, '', $queue);
		$channel->close();
		$connection->close();	
	}
}
